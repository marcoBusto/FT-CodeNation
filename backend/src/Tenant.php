<?php

// Resuelve el tenant de cada request a partir del header X-Tenant-Id.
//
// Esto es una solución temporal (igual criterio que el "usuario placeholder"
// del proyecto de referencia SC-CodeNation): hasta que exista login real, el
// tenant se indica explícito en cada request en vez de resolverse desde una
// sesión. Cuando se construya login, esta clase se reemplaza por la lectura
// del tenant_id del usuario autenticado — ningún controller debería cambiar,
// porque todos reciben el tenant_id ya resuelto como parámetro.
class Tenant
{
    // Lanza una excepción (capturada en index.php) si el header falta, no es
    // numérico, o el tenant no existe / está inactivo — así ningún endpoint
    // puede ejecutarse sin un tenant válido.
    public static function resolverDesdeHeader(?string $valorHeader): int
    {
        if ($valorHeader === null || $valorHeader === '' || !ctype_digit($valorHeader)) {
            throw new DomainException('Falta el header X-Tenant-Id o no es válido.');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id FROM tenants WHERE id = :id AND estado = 'activo'");
        $stmt->execute(['id' => $valorHeader]);
        $tenantId = $stmt->fetchColumn();

        if ($tenantId === false) {
            throw new DomainException('El tenant indicado no existe o está inactivo.');
        }

        return (int) $tenantId;
    }
}
