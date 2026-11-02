"""API CRUD para la tabla mascota de MySQL/XAMPP."""

import os
import re
from datetime import date
from pathlib import Path

import pymysql
from dotenv import load_dotenv
from flask import Flask, abort, g, jsonify, request, url_for
from werkzeug.exceptions import HTTPException

load_dotenv(Path(__file__).with_name('.env'))
FIELDS = ('nombres', 'genero', 'raza', 'fecha_nacimiento', 'especie')
LIMITS = {'nombres': 50, 'genero': 10, 'raza': 50, 'especie': 30}


def create_app(config=None):
    app = Flask(__name__)
    app.json.ensure_ascii = False
    app.config.from_mapping(
        DB_HOST=os.getenv('DB_HOST', '127.0.0.1'),
        DB_PORT=int(os.getenv('DB_PORT', '3306')),
        DB_USER=os.getenv('DB_USER', 'root'),
        DB_PASSWORD=os.getenv('DB_PASSWORD', ''),
        DB_NAME=os.getenv('DB_NAME', 'veterinaria'),
        MAX_CONTENT_LENGTH=16 * 1024,
    )
    if config:
        app.config.update(config)

    def db():
        if 'db' not in g:
            g.db = pymysql.connect(
                host=app.config['DB_HOST'], port=app.config['DB_PORT'],
                user=app.config['DB_USER'], password=app.config['DB_PASSWORD'],
                database=app.config['DB_NAME'], charset='utf8mb4',
                cursorclass=pymysql.cursors.DictCursor,
                connect_timeout=5,
            )
        return g.db

    @app.teardown_appcontext
    def close_db(error):
        connection = g.pop('db', None)
        if connection is not None:
            connection.close()

    @app.errorhandler(HTTPException)
    def http_error(error):
        response = error.get_response()
        response.data = app.json.dumps({'error': error.description})
        response.content_type = 'application/json'
        return response

    @app.errorhandler(pymysql.MySQLError)
    def database_error(error):
        app.logger.exception('Error de MySQL')
        return jsonify(error='No se pudo completar la operación en MySQL. '
                       'Verifica XAMPP, la base de datos y la configuración .env.'), 503

    def serialize(row):
        row = dict(row)
        if isinstance(row.get('fecha_nacimiento'), date):
            row['fecha_nacimiento'] = row['fecha_nacimiento'].isoformat()
        return row

    def find(mascota_id, *, lock=False):
        with db().cursor() as cursor:
            cursor.execute('SELECT id, nombres, genero, raza, fecha_nacimiento, especie '
                           'FROM mascota WHERE id = %s' + (' FOR UPDATE' if lock else ''),
                           (mascota_id,))
            row = cursor.fetchone()
        if row is None:
            abort(404, description='Mascota no encontrada.')
        return serialize(row)

    def validate():
        if not request.is_json:
            abort(415, description='Envía Content-Type: application/json.')
        data = request.get_json()
        if not isinstance(data, dict):
            abort(400, description='El cuerpo debe ser un objeto JSON.')
        errors = {}
        for field in data.keys() - set(FIELDS):
            errors[field] = 'Campo no permitido; el id se genera automáticamente.'
        clean = {}
        for field in FIELDS:
            value = data.get(field)
            if not isinstance(value, str) or not value.strip():
                errors[field] = 'Es obligatorio y debe ser texto no vacío.'
                continue
            value = value.strip()
            if field in LIMITS and len(value) > LIMITS[field]:
                errors[field] = f'Máximo {LIMITS[field]} caracteres.'
            elif field == 'fecha_nacimiento':
                try:
                    if not re.fullmatch(r'\d{4}-\d{2}-\d{2}', value):
                        raise ValueError
                    birthday = date.fromisoformat(value)
                    if birthday > date.today():
                        raise ValueError
                except ValueError:
                    errors[field] = 'Usa una fecha válida YYYY-MM-DD que no sea futura.'
            clean[field] = value
        if errors:
            return None, (jsonify(error='Datos inválidos.', detalles=errors), 400)
        return clean, None

    @app.get('/')
    def index():
        return jsonify(servicio='API Veterinaria', mascotas='/mascotas', salud='/health')

    @app.get('/health')
    def health():
        with db().cursor() as cursor:
            cursor.execute('SELECT id FROM mascota LIMIT 1')
        return jsonify(estado='ok', base_de_datos=app.config['DB_NAME'])

    @app.get('/mascotas')
    def list_mascotas():
        with db().cursor() as cursor:
            cursor.execute('SELECT id, nombres, genero, raza, fecha_nacimiento, especie '
                           'FROM mascota ORDER BY id')
            rows = cursor.fetchall()
        return jsonify([serialize(row) for row in rows])

    @app.get('/mascotas/<int:mascota_id>')
    def get_mascota(mascota_id):
        return jsonify(find(mascota_id))

    @app.post('/mascotas')
    def create_mascota():
        data, error = validate()
        if error:
            return error
        with db().cursor() as cursor:
            cursor.execute('INSERT INTO mascota '
                           '(nombres, genero, raza, fecha_nacimiento, especie) '
                           'VALUES (%s, %s, %s, %s, %s)', tuple(data[f] for f in FIELDS))
            mascota_id = cursor.lastrowid
        row = find(mascota_id)
        db().commit()
        return jsonify(row), 201, {'Location': url_for('get_mascota', mascota_id=mascota_id)}

    @app.put('/mascotas/<int:mascota_id>')
    def update_mascota(mascota_id):
        data, error = validate()
        if error:
            return error
        find(mascota_id, lock=True)
        with db().cursor() as cursor:
            cursor.execute('UPDATE mascota SET nombres=%s, genero=%s, raza=%s, '
                           'fecha_nacimiento=%s, especie=%s WHERE id=%s',
                           (*[data[f] for f in FIELDS], mascota_id))
        row = find(mascota_id)
        db().commit()
        return jsonify(row)

    @app.delete('/mascotas/<int:mascota_id>')
    def delete_mascota(mascota_id):
        with db().cursor() as cursor:
            cursor.execute('DELETE FROM mascota WHERE id=%s', (mascota_id,))
            if cursor.rowcount == 0:
                abort(404, description='Mascota no encontrada.')
        db().commit()
        return '', 204

    return app


app = create_app()

if __name__ == '__main__':
    from waitress import serve
    serve(app, host='127.0.0.1', port=int(os.getenv('PORT', '5000')))
