# DER — Prode de Torneos de Fútbol

Diagrama Entidad-Relación del modelo de datos (11 entidades).
Render: GitHub, VS Code (Markdown Preview Mermaid) o https://mermaid.live (exporta PNG/SVG).

> El **ranking** no es una tabla: se calcula con `SUM(prediccion.puntos) GROUP BY usuario` por torneo.

```mermaid
erDiagram
    usuario ||--o{ inscripcion          : "se inscribe"
    usuario ||--o{ prediccion           : "realiza"
    usuario ||--o{ cuenta_cobro         : "es dueño de"
    usuario ||--o{ premio               : "gana"

    torneo  ||--o{ fecha                : "se compone de"
    torneo  ||--o{ inscripcion          : "recibe"
    torneo  ||--o{ distribucion_premio  : "define"
    torneo  ||--o{ premio               : "reparte"

    cuenta_cobro ||--o{ torneo          : "recauda para"

    fecha   ||--o{ partido              : "agrupa"

    equipo  ||--o{ partido              : "juega (local)"
    equipo  ||--o{ partido              : "juega (visitante)"

    partido ||--o{ prediccion           : "recibe"

    inscripcion ||--|| pago             : "se abona con"

    usuario {
        int id PK
        varchar nombre
        varchar apellido
        varchar email UK
        varchar password_hash
        enum rol "participante|admin"
        tinyint activo
        datetime creado_en
    }

    torneo {
        int id PK
        varchar nombre
        text descripcion
        decimal precio_entrada
        decimal pozo_acumulado
        enum estado "proximo|activo|finalizado"
        enum modo_puntaje "estandar|ponderado"
        int puntos_pozo_partido "NULL si estandar"
        int cuenta_cobro_id FK
        decimal comision_organizador
        enum modo_resultados "api|manual"
        varchar api_proveedor "NULL"
        varchar api_competition_id "NULL"
        date inicio
        date fin
    }

    fecha {
        int id PK
        int torneo_id FK
        int numero
        datetime cierre "clave para countdown"
        enum estado "abierta|cerrada|finalizada"
    }

    equipo {
        int id PK
        varchar nombre
        varchar escudo "opcional"
        varchar api_team_id "NULL"
    }

    partido {
        int id PK
        int fecha_id FK
        int equipo_local_id FK
        int equipo_visitante_id FK
        datetime inicio "corte para predicciones"
        int goles_local "NULL hasta resultado"
        int goles_visitante "NULL hasta resultado"
        enum estado "programado|en_vivo|finalizado"
        enum fuente "api|manual"
        varchar api_fixture_id "NULL"
        datetime actualizado_en "NULL"
    }

    inscripcion {
        int id PK
        int usuario_id FK
        int torneo_id FK
        enum estado_pago "pendiente|pagado|rechazado"
        datetime creado_en
        string uq_usuario_torneo UK "UNIQUE(usuario_id,torneo_id)"
    }

    pago {
        int id PK
        int inscripcion_id FK
        decimal monto
        enum estado "pending|approved|rejected"
        varchar mp_preference_id
        varchar mp_payment_id
        datetime creado_en
    }

    prediccion {
        int id PK
        int usuario_id FK
        int partido_id FK
        int goles_local_pred
        int goles_visitante_pred
        decimal puntos "DEFAULT 0"
        datetime creado_en
        string uq_usuario_partido UK "UNIQUE(usuario_id,partido_id)"
    }

    cuenta_cobro {
        int id PK
        int usuario_id FK
        varchar alias
        varchar mp_collector_id
        varchar mp_access_token "cifrado / fuera del repo"
        tinyint activa
    }

    distribucion_premio {
        int id PK
        int torneo_id FK
        int posicion "1,2,3..."
        decimal porcentaje "suma = 100%"
        string uq_torneo_posicion UK "UNIQUE(torneo_id,posicion)"
    }

    premio {
        int id PK
        int torneo_id FK
        int usuario_id FK
        int posicion
        decimal monto
        datetime creado_en
    }
```

## Lectura rápida de la columna vertebral

```
torneo (1)──< fecha (1)──< partido (1)──< prediccion >──(1) usuario
```

Un torneo tiene varias fechas, cada fecha varios partidos, cada partido una predicción por
participante. Sobre esa espina dorsal se apoyan:

- **Inscripción/pago:** `usuario ──< inscripcion >── torneo`, y cada `inscripcion ──── pago` (1:1).
- **Recaudación:** `cuenta_cobro ──< torneo` (la cuenta de MP donde recauda el organizador).
- **Premiación:** `torneo ──< distribucion_premio` (reglas: qué % por puesto) y
  `torneo ──< premio >── usuario` (montos ya repartidos al finalizar).

## Notas de cardinalidad

| Relación | Tipo | Nota |
|---|---|---|
| usuario – torneo | N:M | resuelta por `inscripcion` |
| inscripcion – pago | 1:1 | a lo sumo un pago aprobado por inscripción |
| equipo – partido | 1:N (×2) | un equipo juega como local **y** como visitante |
| torneo – distribucion_premio | 1:N | un % por puesto; la suma debe dar 100% |
```
