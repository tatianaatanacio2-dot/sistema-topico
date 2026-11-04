# Tópico · Salud y cuidado

Primera versión con Laravel 13, MySQL, Vue 3 y Quasar. Incluye pacientes, solicitudes, atenciones con signos vitales, inventario por lotes, entregas, derivaciones, reportes CSV y usuarios por rol.

## Iniciar en este equipo

1. Usa la instalación de XAMPP que contiene la base `topico`: `C:/Users/PC001/Downloads/esktop/OFFICE`. El MySQL de `C:/xampp` corresponde a otra instalación y no contiene estos datos.
2. Desde la raíz del proyecto ejecuta `./iniciar.ps1` en PowerShell.
3. Abre `http://localhost:9000`.

El servidor usa el PHP 8.4 independiente ubicado en `.runtime/php`; el PHP 8.2 incluido en XAMPP no cumple el requisito de este Laravel. Los registros de arranque y las credenciales iniciales están en `.local`, fuera de Git. La configuración de conexión se conserva en `backend/.env`.

Si el puerto 3306 está libre, `iniciar.ps1` enciende el MySQL de la instalación que contiene la base `topico`. Si ya está ocupado, asegúrate de que corresponda a esa instalación.

Para crear otro coordinador desde la consola, usa el PHP compatible: `../.runtime/php/php.exe artisan topico:coordinator correo@institucion.edu --no-interaction` desde `backend`. El comando genera una contraseña aleatoria y no modifica usuarios existentes.

## Primeros registros

Registra los pacientes y sus contactos en **Pacientes**. Crea los accesos en **Usuarios y permisos**; una cuenta con rol Paciente debe estar vinculada a su ficha. Los docentes pueden reportar alumnos registrados y consultar sus propias solicitudes.

En **Solicitudes**, usa **Atender** para abrir una atención. Registra los signos vitales y cuidados; las acciones de medicamento y derivación se realizan mientras la atención está abierta. Guarda la atención como **Finalizada** al concluir. Los registros finalizados se conservan sin edición.

En **Medicamentos**, crea el catálogo y después registra ingresos con lote, cantidad y vencimiento. Las entregas descuentan el lote en una transacción y dejan registrado al responsable. Los lotes vencidos o que vencen hoy quedan excluidos del disponible. El panel avisa sobre stock bajo y vencimientos dentro de 30 días.

Los reportes se filtran por fecha de creación de la atención y tipo de paciente. Los detalles de atención y derivación tienen opción de impresión mediante el navegador. Las notificaciones automáticas por correo y la publicación en un servidor externo quedan fuera de esta versión.

No se crearon ni ejecutaron pruebas funcionales o automatizadas por indicación del usuario. La compilación y la comprobación de tipos se ejecutaron como parte de la preparación de la aplicación.

## Install the dependencies

```bash
pnpm install
# or: yarn/npm/bun install
```

### Start the app in development mode (HMR, error reporting, etc.)

```bash
quasar dev
```

### Format & Lint the files

```bash
pnpm run lint
# or: yarn/npm/bun run lint
```

...or just check formatting & linting:

```bash
pnpm run lint:check
# or: yarn/npm/bun run lint:check
```

### Build the app for production

```bash
quasar build
```

### Customize the configuration

See [Configuring quasar.config.js](https://v2.quasar.dev/quasar-cli-vite/quasar-config-file).
