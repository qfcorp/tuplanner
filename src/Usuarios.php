<?php
declare(strict_types=1);

namespace TuPlanner;

use PDO;
use RuntimeException;

/**
 * Usuarios del negocio (los crea el dueño):
 *  - vendedor: ventas, cotizaciones, contratos y cobros
 *  - coordinador: solo lo del día del evento (sin dinero)
 *  - consulta: ve todo, no cambia nada (contador, socio)
 * El plan limita cuántos usuarios activos puede tener el negocio.
 */
final class Usuarios
{
    public const ROLES = [
        'dueno' => 'Dueño', 'vendedor' => 'Vendedor', 'coordinador' => 'Coordinador', 'consulta' => 'Solo consulta',
    ];
    public const ROLES_AYUDA = [
        'dueno'       => 'Todo, incluida la configuración, los usuarios y el plan.',
        'vendedor'    => 'Atiende consultas, cotiza, hace contratos y registra cobros.',
        'coordinador' => 'Ve los eventos y organiza el día: personal, proveedores, invitados y mobiliario. No ve dinero.',
        'consulta'    => 'Ve todo (eventos, cobros, reportes) pero no puede cambiar nada. Ideal para el contador o un socio.',
    ];
    /** Roles que el dueño puede crear o asignar. */
    public const ROLES_ASIGNABLES = ['vendedor', 'coordinador', 'consulta'];

    public function __construct(private PDO $db) {}

    public function lista(int $negocioId): array
    {
        $st = $this->db->prepare("SELECT id, nombre, email, telefono, rol, activo, telegram_chat_id IS NOT NULL AS telegram,
                                         clave_temporal, creado_en, ultimo_ingreso
                                    FROM usuarios WHERE negocio_id = ?
                                ORDER BY activo DESC, CASE rol WHEN 'dueno' THEN 0 WHEN 'vendedor' THEN 1 WHEN 'coordinador' THEN 2 ELSE 3 END, nombre");
        $st->execute([$negocioId]);
        return $st->fetchAll();
    }

    public function obtener(int $negocioId, int $usuarioId): ?array
    {
        $st = $this->db->prepare('SELECT * FROM usuarios WHERE id = ? AND negocio_id = ?');
        $st->execute([$usuarioId, $negocioId]);
        return $st->fetch() ?: null;
    }

    /** Contraseña temporal fácil de dictar (sin 0/O ni 1/l). */
    public static function claveTemporal(): string
    {
        $letras = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $c = '';
        for ($i = 0; $i < 10; $i++) $c .= $letras[random_int(0, strlen($letras) - 1)];
        return $c;
    }

    private function mensajeLimite(int $negocioId): string
    {
        $st = $this->db->prepare('SELECT p.nombre, p.max_usuarios FROM negocios n JOIN planes p ON p.codigo = n.plan_codigo WHERE n.id = ?');
        $st->execute([$negocioId]);
        $p = $st->fetch() ?: ['nombre' => '', 'max_usuarios' => 0];
        return 'Tu plan ' . $p['nombre'] . ' permite ' . (int) $p['max_usuarios'] . ' usuarios activos (contándote a ti). '
             . 'Desactiva a alguien que ya no use el sistema o pásate al plan Quinta para tener usuarios sin límite.';
    }

    /**
     * Crea un usuario con una contraseña temporal (se muestra una sola vez al dueño).
     * @return array{id:int, clave:string, email:string, nombre:string}
     */
    public function crear(int $negocioId, string $nombre, string $email, string $telefono, string $rol): array
    {
        $nombre = trim($nombre);
        $email = strtolower(trim($email));
        $telefono = trim($telefono);
        if (mb_strlen($nombre) < 2) throw new RuntimeException('Escribe el nombre de la persona.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('El correo no es válido. Con ese correo va a entrar al sistema.');
        if (!in_array($rol, self::ROLES_ASIGNABLES, true)) throw new RuntimeException('Elige qué va a hacer: vendedor, coordinador o solo consulta.');
        if ($telefono !== '' && WhatsApp::normalizarTelefono($telefono) === null) throw new RuntimeException('El celular no es válido. Escríbelo así: 0991234567.');
        $st = $this->db->prepare('SELECT 1 FROM usuarios WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetchColumn()) throw new RuntimeException('Ya existe un usuario con ese correo (en tu negocio o en otro). Usa otro correo.');
        if (!(new Planes($this->db))->puedeAgregarUsuario($negocioId)) throw new RuntimeException($this->mensajeLimite($negocioId));
        $clave = self::claveTemporal();
        $st = $this->db->prepare('INSERT INTO usuarios (negocio_id, nombre, email, telefono, password_hash, rol, clave_temporal)
                                  VALUES (?,?,?,?,?,?, true) RETURNING id');
        $st->execute([$negocioId, mb_substr($nombre, 0, 100), $email, $telefono ?: null, password_hash($clave, PASSWORD_DEFAULT), $rol]);
        return ['id' => (int) $st->fetchColumn(), 'clave' => $clave, 'email' => $email, 'nombre' => $nombre];
    }

    /** Usuario de otro (no el dueño, no uno mismo) que se puede cambiar. */
    private function cambiable(int $negocioId, int $usuarioId, int $quienId): array
    {
        $u = $this->obtener($negocioId, $usuarioId);
        if (!$u) throw new RuntimeException('No se encontró ese usuario.');
        if ($usuarioId === $quienId) throw new RuntimeException('No puedes quitarte permisos ni desactivarte a ti mismo.');
        if ($u['rol'] === 'dueno') throw new RuntimeException('La cuenta del dueño no se puede cambiar desde aquí. Escríbenos si necesitas pasarla a otra persona.');
        return $u;
    }

    public function cambiarRol(int $negocioId, int $usuarioId, string $rol, int $quienId): void
    {
        $this->cambiable($negocioId, $usuarioId, $quienId);
        if (!in_array($rol, self::ROLES_ASIGNABLES, true)) throw new RuntimeException('Elige vendedor, coordinador o solo consulta.');
        $this->db->prepare('UPDATE usuarios SET rol = ? WHERE id = ? AND negocio_id = ?')->execute([$rol, $usuarioId, $negocioId]);
    }

    public function activar(int $negocioId, int $usuarioId, bool $activo, int $quienId): void
    {
        $u = $this->cambiable($negocioId, $usuarioId, $quienId);
        if ($activo && !$u['activo'] && !(new Planes($this->db))->puedeAgregarUsuario($negocioId)) {
            throw new RuntimeException($this->mensajeLimite($negocioId));
        }
        $this->db->prepare('UPDATE usuarios SET activo = ? WHERE id = ? AND negocio_id = ?')->execute([$activo ? 'true' : 'false', $usuarioId, $negocioId]);
    }

    /** El dueño le da una contraseña nueva a alguien que la olvidó. */
    public function nuevaClave(int $negocioId, int $usuarioId, int $quienId): array
    {
        $u = $this->cambiable($negocioId, $usuarioId, $quienId);
        $clave = self::claveTemporal();
        $this->db->prepare('UPDATE usuarios SET password_hash = ?, clave_temporal = true WHERE id = ? AND negocio_id = ?')
                 ->execute([password_hash($clave, PASSWORD_DEFAULT), $usuarioId, $negocioId]);
        return ['id' => $usuarioId, 'clave' => $clave, 'email' => $u['email'], 'nombre' => $u['nombre']];
    }

    // ------------------------------------------------------------------
    // Mi cuenta
    // ------------------------------------------------------------------

    public function guardarPerfil(int $usuarioId, string $nombre, string $telefono): void
    {
        $nombre = trim($nombre);
        $telefono = trim($telefono);
        if (mb_strlen($nombre) < 2) throw new RuntimeException('Escribe tu nombre.');
        if ($telefono !== '' && WhatsApp::normalizarTelefono($telefono) === null) throw new RuntimeException('El celular no es válido. Escríbelo así: 0991234567.');
        $this->db->prepare('UPDATE usuarios SET nombre = ?, telefono = ? WHERE id = ?')->execute([mb_substr($nombre, 0, 100), $telefono ?: null, $usuarioId]);
    }

    public function cambiarClave(int $usuarioId, string $actual, string $nueva, string $repetir): void
    {
        $st = $this->db->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
        $st->execute([$usuarioId]);
        $hash = (string) $st->fetchColumn();
        if ($hash === '' || !password_verify($actual, $hash)) throw new RuntimeException('La contraseña actual no es correcta.');
        if (strlen($nueva) < 8) throw new RuntimeException('La contraseña nueva debe tener al menos 8 caracteres.');
        if ($nueva !== $repetir) throw new RuntimeException('Las dos contraseñas nuevas no son iguales. Escríbelas otra vez.');
        if ($nueva === $actual) throw new RuntimeException('La contraseña nueva debe ser distinta de la actual.');
        $this->db->prepare('UPDATE usuarios SET password_hash = ?, clave_temporal = false WHERE id = ?')
                 ->execute([password_hash($nueva, PASSWORD_DEFAULT), $usuarioId]);
    }
}
