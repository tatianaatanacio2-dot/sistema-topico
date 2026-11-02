# Veterinaria · Semana 10

Backend Python con Flask y MySQL (XAMPP) para administrar la tabla `mascota`.

## Iniciar en Windows

1. En XAMPP, activa **MySQL**. Apache es necesario para phpMyAdmin, pero la API funciona con su propio servidor Python.
2. Si ya tienes `veterinaria.mascota` con los campos indicados abajo, conserva tu tabla. Para una instalación nueva, importa `database.sql` desde phpMyAdmin. El script no elimina tablas ni registros existentes.
3. Abre PowerShell en esta carpeta y ejecuta:

```powershell
powershell -ExecutionPolicy Bypass -File .\iniciar.ps1
```

El script crea `.venv`, instala las dependencias, copia `.env.example` a `.env` si no existe y arranca la API. Requiere Python 3.10 o superior e internet para instalar paquetes.

La configuración inicial usa MySQL en `127.0.0.1:3306`, usuario `root`, contraseña vacía y base `veterinaria`, como en esta instalación local de XAMPP. Si tu conexión es diferente, modifica `.env` y reinicia el servidor. No publiques `.env`.

Para iniciar manualmente después de instalar:

```powershell
.\.venv\Scripts\python.exe app.py
```

Abre `http://127.0.0.1:5000/health` para comprobar la conexión y `http://127.0.0.1:5000/mascotas` para ver los registros. Detén el servidor con **Ctrl+C**. Si el puerto está ocupado, cambia `PORT` en `.env` y `base_url` en Postman.

## Tabla y JSON

| Campo | Tipo MySQL | Regla de la API |
|---|---|---|
| id | INT PRIMARY KEY AUTO_INCREMENT | Se genera al crear |
| nombres | VARCHAR(50) | Texto obligatorio |
| genero | VARCHAR(10) | Texto obligatorio, por ejemplo Hembra o Macho |
| raza | VARCHAR(50) | Texto obligatorio |
| fecha_nacimiento | DATE | YYYY-MM-DD, fecha válida que no sea futura |
| especie | VARCHAR(30) | Texto obligatorio |

POST y PUT requieren los cinco campos de texto; PUT reemplaza todos los datos editables. No envíes `id` en el cuerpo. Ejemplo:

```json
{
  "nombres": "Luna",
  "genero": "Hembra",
  "raza": "Labrador",
  "fecha_nacimiento": "2023-05-10",
  "especie": "Perro"
}
```

## Probar desde Postman

Importa `Veterinaria.postman_collection.json` con **Import → Files**. La colección define `base_url = http://127.0.0.1:5000` y guarda automáticamente en `mascota_id` el ID devuelto por POST. Ejecuta las solicitudes en orden, o utiliza **Run collection**: crea una mascota de demostración, la consulta, la actualiza y finalmente la elimina. No usa los IDs de tus mascotas existentes.

| Método | URL | Resultado |
|---|---|---|
| GET | `{{base_url}}/health` | 200, estado de MySQL y tabla |
| GET | `{{base_url}}/mascotas` | 200, arreglo de mascotas (puede estar vacío) |
| POST | `{{base_url}}/mascotas` | 201, mascota creada y cabecera Location |
| GET | `{{base_url}}/mascotas/{{mascota_id}}` | 200, una mascota |
| PUT | `{{base_url}}/mascotas/{{mascota_id}}` | 200, mascota actualizada |
| DELETE | `{{base_url}}/mascotas/{{mascota_id}}` | 204, sin cuerpo |

En solicitudes POST y PUT manuales selecciona **Body → raw → JSON** y pega el ejemplo. Postman enviará `Content-Type: application/json`.

Errores: 400 para datos inválidos o JSON mal formado; 404 para una mascota inexistente; 405 para un método no permitido; 413 para un cuerpo demasiado grande; 415 si no se envía JSON; 503 si MySQL no puede completar la operación. Las respuestas de error son JSON.

## Verificación

Con MySQL encendido y `.env` configurado:

```powershell
.\.venv\Scripts\python.exe -m unittest -v
```

Las pruebas verifican el ciclo CRUD real, cambios con el mismo valor, Unicode, fechas, validación, registros inexistentes y errores de conexión. Crean registros propios y los eliminan al finalizar; no borran datos previos. Los IDs autoincrementales consumidos no se reutilizan.

## GitHub

Versiona el código, `requirements.txt`, `.env.example`, SQL, pruebas y la colección Postman. `.gitignore` excluye contraseñas de `.env`, el entorno virtual y los registros locales.

Este proyecto es una API local para la práctica, accesible solo desde este equipo. Publicar el código en GitHub no ejecuta el servidor; para alojarla públicamente se requiere un servicio de hosting, MySQL y controles de acceso.
