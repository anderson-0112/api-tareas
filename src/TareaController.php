<?php
/**
 * TareaController — Controlador del recurso "tareas".
 * FASE 5: implementa el CRUD real contra MySQL usando PDO
 * y sentencias preparadas en toda consulta con datos del usuario.
 */

require_once __DIR__ . '/Database.php';

class TareaController
{
    private PDO $db;

    // Columnas que el cliente PUEDE enviar o modificar (lista blanca).
    // id, fecha_creacion y fecha_actualizacion NO están: los gestiona la BD.
    private const CAMPOS_PERMITIDOS = [
        'titulo', 'descripcion', 'estado', 'prioridad',
        'responsable', 'categoria', 'fecha_vencimiento', 'tiempo_estimado'
    ];

    private const ESTADOS_VALIDOS     = ['pendiente', 'en progreso', 'completada', 'cancelada'];
    private const PRIORIDADES_VALIDAS = ['alta', 'media', 'baja'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // =====================================================
    //  LECTURA
    // =====================================================

    // GET /tareas  → lista todas las tareas
    public function index(): void
    {
        // Sin datos del usuario: query() directo es seguro y suficiente.
        $stmt   = $this->db->query('SELECT * FROM tareas ORDER BY id DESC');
        $tareas = $stmt->fetchAll();

        $this->responder(200, $tareas);
    }

    // GET /tareas/{id}  → una tarea por su ID
    public function show(string $id): void
    {
        $tarea = $this->buscarPorId($id);

        if (!$tarea) {
            $this->responder(404, ['error' => 'Tarea no encontrada']);
        }

        $this->responder(200, $tarea);
    }

    // =====================================================
    //  ESCRITURA
    // =====================================================

    // POST /tareas  → crea una tarea
    public function store(): void
    {
        $datos   = $this->obtenerCuerpo();
        $errores = $this->validar($datos, true); // true = es creación

        if ($errores) {
            $this->responder(400, ['error' => 'Datos inválidos', 'detalles' => $errores]);
        }

        $sql = 'INSERT INTO tareas
                    (titulo, descripcion, estado, prioridad, responsable,
                     categoria, fecha_vencimiento, tiempo_estimado)
                VALUES
                    (:titulo, :descripcion, :estado, :prioridad, :responsable,
                     :categoria, :fecha_vencimiento, :tiempo_estimado)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'titulo'            => trim($datos['titulo']),
            'descripcion'       => $datos['descripcion']      ?? null,
            'estado'            => $datos['estado']           ?? 'pendiente',
            'prioridad'         => $datos['prioridad']        ?? 'media',
            'responsable'       => $datos['responsable']      ?? null,
            'categoria'         => $datos['categoria']        ?? null,
            'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
            'tiempo_estimado'   => $datos['tiempo_estimado']  ?? null,
        ]);

        // Devolvemos el recurso recién creado con su ID asignado.
        $nuevoId = (int) $this->db->lastInsertId();
        $tarea   = $this->buscarPorId((string) $nuevoId);

        $this->responder(201, $tarea); // 201 Created
    }

    // PUT/PATCH /tareas/{id}  → actualiza total o parcialmente
    public function update(string $id): void
    {
        // 1) La tarea debe existir
        if (!$this->buscarPorId($id)) {
            $this->responder(404, ['error' => 'Tarea no encontrada']);
        }

        $datos = $this->obtenerCuerpo();

        // 2) Nos quedamos SOLO con campos permitidos y presentes.
        //    Esto ignora cualquier campo extra o prohibido (ej. "id").
        $cambios = array_intersect_key($datos, array_flip(self::CAMPOS_PERMITIDOS));

        if (empty($cambios)) {
            $this->responder(400, ['error' => 'No se enviaron campos válidos para actualizar']);
        }

        // 3) Validamos solo lo que llegó (false = no es creación)
        $errores = $this->validar($cambios, false);
        if ($errores) {
            $this->responder(400, ['error' => 'Datos inválidos', 'detalles' => $errores]);
        }

        // 4) Construcción dinámica del SET.
        //    SEGURA porque los nombres de columna vienen de la lista blanca,
        //    NUNCA del usuario. Los valores viajan como parámetros preparados.
        $sets   = [];
        $params = ['id' => $id];

        foreach ($cambios as $columna => $valor) {
            $sets[] = "$columna = :$columna";
            // Un string vacío en un campo opcional se guarda como NULL
            $params[$columna] = ($valor === '') ? null : $valor;
        }
        if (isset($params['titulo'])) {
            $params['titulo'] = trim($params['titulo']);
        }

        $sql  = 'UPDATE tareas SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $this->responder(200, $this->buscarPorId($id));
    }

    // DELETE /tareas/{id}  → elimina una tarea
    public function destroy(string $id): void
    {
        if (!$this->buscarPorId($id)) {
            $this->responder(404, ['error' => 'Tarea no encontrada']);
        }

        $stmt = $this->db->prepare('DELETE FROM tareas WHERE id = :id');
        $stmt->execute(['id' => $id]);

        // Para usar 204 en su lugar: http_response_code(204); exit;
        $this->responder(200, [
            'mensaje' => 'Tarea eliminada correctamente',
            'id'      => (int) $id
        ]);
    }

    // =====================================================
    //  MÉTODOS INTERNOS (privados)
    // =====================================================

    // Busca una tarea por ID. Devuelve el arreglo o null si no existe.
    private function buscarPorId(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tareas WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $tarea = $stmt->fetch();

        return $tarea ?: null;
    }

    // Lee y decodifica el cuerpo JSON de la petición.
    private function obtenerCuerpo(): array
    {
        $crudo = file_get_contents('php://input');
        $datos = json_decode($crudo, true);

        if (!is_array($datos)) {
            $this->responder(400, ['error' => 'El cuerpo debe ser un JSON válido']);
        }

        return $datos;
    }

    // Valida los datos. Devuelve un arreglo de errores (vacío si todo bien).
    private function validar(array $datos, bool $esCreacion): array
    {
        $errores = [];

        // titulo: obligatorio al crear; si viene, no vacío y <= 150
        if ($esCreacion && !array_key_exists('titulo', $datos)) {
            $errores[] = 'El campo "titulo" es obligatorio';
        }
        if (array_key_exists('titulo', $datos)) {
            $titulo = is_string($datos['titulo']) ? trim($datos['titulo']) : '';
            if ($titulo === '') {
                $errores[] = 'El campo "titulo" es obligatorio y no puede estar vacío';
            } elseif (mb_strlen($titulo) > 150) {
                $errores[] = 'El campo "titulo" no debe superar los 150 caracteres';
            }
        }

        // estado: si viene, debe pertenecer al dominio
        if (array_key_exists('estado', $datos)
            && !in_array($datos['estado'], self::ESTADOS_VALIDOS, true)) {
            $errores[] = 'El campo "estado" debe ser uno de: ' . implode(', ', self::ESTADOS_VALIDOS);
        }

        // prioridad: si viene, debe pertenecer al dominio
        if (array_key_exists('prioridad', $datos)
            && !in_array($datos['prioridad'], self::PRIORIDADES_VALIDAS, true)) {
            $errores[] = 'El campo "prioridad" debe ser una de: ' . implode(', ', self::PRIORIDADES_VALIDAS);
        }

        // tiempo_estimado: numérico >= 0
        if (array_key_exists('tiempo_estimado', $datos) && $datos['tiempo_estimado'] !== null) {
            if (!is_numeric($datos['tiempo_estimado']) || $datos['tiempo_estimado'] < 0) {
                $errores[] = 'El campo "tiempo_estimado" debe ser un número mayor o igual a 0';
            }
        }

        // fecha_vencimiento: formato AAAA-MM-DD si viene
        if (array_key_exists('fecha_vencimiento', $datos)
            && $datos['fecha_vencimiento'] !== null
            && $datos['fecha_vencimiento'] !== '') {
            $f = DateTime::createFromFormat('Y-m-d', $datos['fecha_vencimiento']);
            if (!$f || $f->format('Y-m-d') !== $datos['fecha_vencimiento']) {
                $errores[] = 'El campo "fecha_vencimiento" debe tener el formato AAAA-MM-DD';
            }
        }

        return $errores;
    }

    // Responde en JSON con el código HTTP indicado y termina la ejecución.
    private function responder(int $codigo, $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }
}