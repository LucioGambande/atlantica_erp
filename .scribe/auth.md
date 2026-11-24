# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_SANCTUM_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Token Sanctum emitido con <code>php artisan integrations:create-token "Nombre" --permission="manage X"</code>. El token solo puede llamar a los endpoints cubiertos por los permisos otorgados.
