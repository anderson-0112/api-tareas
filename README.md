# API REST de Gestión de Tareas

API RESTful desarrollada en PHP puro (sin frameworks) con persistencia en MySQL,
como proyecto de la asignatura Desarrollo Web / Backend.

## URL Base de Producción

https://api.puntosportcunen.com

## Tecnologías utilizadas

- **Lenguaje:** PHP 
- **Base de datos:** MySQL 
- **Hosting:** Hostinger (hosting compartido)
- **Formato de datos:** JSON

## Tabla de rutas (Endpoints)

| Método | Ruta | Descripción | Parámetro URL |
|--------|------|-------------|---------------|
| GET | `/tareas` | Lista todas las tareas | Ninguno |
| GET | `/tareas/{id}` | Obtiene una tarea por su ID | `id` (entero, obligatorio) |
| POST | `/tareas` | Crea una nueva tarea | Ninguno |
| PUT / PATCH | `/tareas/{id}` | Actualiza una tarea (total o parcial) | `id` (entero, obligatorio) |
| DELETE | `/tareas/{id}` | Elimina una tarea | `id` (entero, obligatorio) |

## Modelo de datos: Tarea

| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `id` | entero | Autogenerado | Identificador único |
| `titulo` | texto (máx. 150) | **Sí** | Nombre breve de la tarea |
| `descripcion` | texto | No | Detalle de la tarea |
| `estado` | texto | No (default: `pendiente`) | Uno de: `pendiente`, `en progreso`, `completada`, `cancelada` |
| `prioridad` | texto | No (default: `media`) | Uno de: `alta`, `media`, `baja` |
| `responsable` | texto | No | Persona asignada |
| `categoria` | texto | No | Proyecto o categoría |
| `fecha_vencimiento` | fecha (`AAAA-MM-DD`) | No | Fecha límite |
| `tiempo_estimado` | numérico | No | Horas/días estimados (≥ 0) |
| `fecha_creacion` | fecha y hora | Autogenerado | Se asigna al crear |
| `fecha_actualizacion` | fecha y hora | Autogenerado | Se actualiza en cada modificación |

## Endpoints en detalle

### 1. Listar todas las tareas

**GET** `/tareas`

**Respuesta exitosa — 200 OK**
```json
[
  {
    "id": 1,
    "titulo": "Configurar servidor",
    "descripcion": "Preparar el entorno de despliegue en Hostinger",
    "estado": "en progreso",
    "prioridad": "alta",
    "responsable": "Juan Perez",
    "categoria": "Infraestructura",
    "fecha_vencimiento": "2026-09-15",
    "tiempo_estimado": "4.50",
    "fecha_creacion": "2026-09-03 18:50:58",
    "fecha_actualizacion": "2026-09-03 18:50:58"
  }
]
```

---

### 2. Obtener una tarea por ID

**GET** `/tareas/{id}`

**Respuesta exitosa — 200 OK**
```json
{
  "id": 1,
  "titulo": "Configurar servidor",
  "descripcion": "Preparar el entorno de despliegue en Hostinger",
  "estado": "en progreso",
  "prioridad": "alta",
  "responsable": "Juan Perez",
  "categoria": "Infraestructura",
  "fecha_vencimiento": "2026-09-15",
  "tiempo_estimado": "4.50",
  "fecha_creacion": "2026-09-03 18:50:58",
  "fecha_actualizacion": "2026-09-03 18:50:58"
}
```

**Respuesta de error — 404 Not Found** (el ID no existe)
```json
{ "error": "Tarea no encontrada" }
```

---

### 3. Crear una nueva tarea

**POST** `/tareas`

**Cuerpo de la petición (JSON de entrada)**
```json
{
  "titulo": "Escribir pruebas unitarias",
  "descripcion": "Cubrir el controlador de tareas",
  "estado": "pendiente",
  "prioridad": "alta",
  "responsable": "Anderson",
  "categoria": "Backend",
  "fecha_vencimiento": "2026-09-20",
  "tiempo_estimado": 3.5
}
```

Solo `titulo` es obligatorio; el resto es opcional.

**Respuesta exitosa — 201 Created**
```json
{
  "id": 4,
  "titulo": "Escribir pruebas unitarias",
  "descripcion": "Cubrir el controlador de tareas",
  "estado": "pendiente",
  "prioridad": "alta",
  "responsable": "Anderson",
  "categoria": "Backend",
  "fecha_vencimiento": "2026-09-20",
  "tiempo_estimado": "3.50",
  "fecha_creacion": "2026-09-04 10:00:00",
  "fecha_actualizacion": "2026-09-04 10:00:00"
}
```

**Respuesta de error — 400 Bad Request** (falta el título)
```json
{
  "error": "Datos inválidos",
  "detalles": ["El campo \"titulo\" es obligatorio"]
}
```

---

### 4. Actualizar una tarea (total o parcial)

**PUT / PATCH** `/tareas/{id}`

**Cuerpo de la petición (JSON de entrada — solo los campos a modificar)**
```json
{
  "estado": "completada",
  "tiempo_estimado": 5
}
```

**Respuesta exitosa — 200 OK**
```json
{
  "id": 4,
  "titulo": "Escribir pruebas unitarias",
  "descripcion": "Cubrir el controlador de tareas",
  "estado": "completada",
  "prioridad": "alta",
  "responsable": "Anderson",
  "categoria": "Backend",
  "fecha_vencimiento": "2026-09-20",
  "tiempo_estimado": "5.00",
  "fecha_creacion": "2026-09-04 10:00:00",
  "fecha_actualizacion": "2026-09-04 10:15:00"
}
```

**Respuesta de error — 404 Not Found**
```json
{ "error": "Tarea no encontrada" }
```

---

### 5. Eliminar una tarea

**DELETE** `/tareas/{id}`

**Respuesta exitosa — 200 OK**
```json
{
  "mensaje": "Tarea eliminada correctamente",
  "id": 4
}
```

**Respuesta de error — 404 Not Found**
```json
{ "error": "Tarea no encontrada" }
```

## Códigos de estado HTTP utilizados

| Código | Significado | Cuándo se produce |
|--------|-------------|---------------------|
| 200 | OK | Consulta, actualización o eliminación exitosa |
| 201 | Created | Tarea creada exitosamente |
| 400 | Bad Request | Datos inválidos o faltantes |
| 404 | Not Found | Recurso o tarea inexistente |
| 405 | Method Not Allowed | Verbo HTTP no soportado |
| 500 | Internal Server Error | Error inesperado del servidor |
