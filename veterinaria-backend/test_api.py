"""Pruebas de integración contra MySQL; eliminan solo las mascotas que crean."""

import unittest

import pymysql

from app import create_app


class ApiTests(unittest.TestCase):
    def setUp(self):
        self.app = create_app({'TESTING': True})
        self.client = self.app.test_client()
        self.created = []
        self.payload = {
            'nombres': 'Prueba API Ñandú', 'genero': 'Hembra',
            'raza': 'Mestiza', 'fecha_nacimiento': '2023-05-10', 'especie': 'Perro',
        }

    def tearDown(self):
        for mascota_id in self.created:
            self.client.delete(f'/mascotas/{mascota_id}')

    def create(self):
        response = self.client.post('/mascotas', json=self.payload)
        self.assertEqual(response.status_code, 201, response.get_json())
        mascota_id = response.get_json()['id']
        self.created.append(mascota_id)
        return mascota_id, response

    def test_crud_and_unchanged_update(self):
        mascota_id, response = self.create()
        path = f'/mascotas/{mascota_id}'
        self.assertEqual(response.headers['Location'], path)
        self.assertEqual(response.get_json()['fecha_nacimiento'], '2023-05-10')
        self.assertEqual(self.client.get(path).get_json()['nombres'], self.payload['nombres'])
        listing = self.client.get('/mascotas')
        self.assertEqual(listing.status_code, 200)
        self.assertIn(mascota_id, [row['id'] for row in listing.get_json()])
        self.assertEqual(self.client.put(path, json=self.payload).status_code, 200)
        updated = {**self.payload, 'nombres': "Luna O'Connor"}
        self.assertEqual(self.client.put(path, json=updated).status_code, 200)
        self.assertEqual(self.client.get(path).get_json()['nombres'], updated['nombres'])
        deleted = self.client.delete(path)
        self.assertEqual(deleted.status_code, 200)
        self.assertEqual(deleted.get_json(), {
            'mensaje': 'Mascota eliminada correctamente.', 'id': mascota_id,
        })
        self.assertEqual(self.client.get(path).status_code, 404)
        self.assertEqual(self.client.put(path, json=updated).status_code, 404)
        self.assertEqual(self.client.delete(path).status_code, 404)

    def test_invalid_data_is_not_saved_or_applied(self):
        mascota_id, _ = self.create()
        invalid = [
            {}, [], None,
            {**self.payload, 'id': 1},
            {**self.payload, 'nombres': ' '},
            {**self.payload, 'nombres': 'x' * 51},
            {**self.payload, 'genero': 123},
            {**self.payload, 'fecha_nacimiento': '2023-02-30'},
            {**self.payload, 'fecha_nacimiento': '20230510'},
            {**self.payload, 'fecha_nacimiento': '2999-01-01'},
        ]
        for payload in invalid:
            with self.subTest(payload=payload):
                for method, path in [('post', '/mascotas'), ('put', f'/mascotas/{mascota_id}')]:
                    response = getattr(self.client, method)(path, data=self.app.json.dumps(payload),
                                                           content_type='application/json')
                    self.assertEqual(response.status_code, 400)
                    self.assertIn('error', response.get_json())
        self.assertEqual(self.client.get(f'/mascotas/{mascota_id}').get_json()['nombres'],
                         self.payload['nombres'])

    def test_http_errors_and_health(self):
        self.assertEqual(self.client.get('/health').status_code, 200)
        self.assertEqual(self.client.post('/mascotas', data='texto').status_code, 415)
        response = self.client.post('/mascotas', data='{', content_type='application/json')
        self.assertEqual(response.status_code, 400)
        self.assertTrue(response.is_json)
        self.assertEqual(self.client.patch('/mascotas').status_code, 405)
        self.assertEqual(self.client.get('/ruta-inexistente').status_code, 404)

    def test_database_error_is_json(self):
        from unittest.mock import patch
        with patch('app.pymysql.connect', side_effect=pymysql.OperationalError(2003, 'test')):
            with self.assertLogs(self.app.logger, level='ERROR'):
                response = self.client.get('/health')
        self.assertEqual(response.status_code, 503)
        self.assertNotIn('test', response.get_json()['error'])


if __name__ == '__main__':
    unittest.main(verbosity=2)
