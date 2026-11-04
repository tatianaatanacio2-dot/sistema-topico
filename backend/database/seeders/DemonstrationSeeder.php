<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemonstrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $coordinator = DB::table('users')->where('email', 'coordinador@topico.local')->where('role', 'coordinator')->lockForUpdate()->first();
            $staff = DB::table('users')->where('email', 'encargado.demo@topico.local')->where('role', 'staff')->first();
            $teacher = DB::table('users')->where('email', 'docente.demo@topico.local')->where('role', 'teacher')->first();
            $patientUser = DB::table('users')->where('email', 'paciente.demo@topico.local')->where('role', 'patient')->first();
            if (! $coordinator || ! $staff || ! $teacher || ! $patientUser) {
                throw new RuntimeException('Se necesitan las cuatro cuentas de demostración existentes.');
            }
            if (DB::table('audit_logs')->where('action', 'seed_demo_20261028')->where('entity', 'demo_dataset')->exists()) {
                $this->command?->info('Los ejemplos ya estaban cargados. No se duplicó ni modificó información.');

                return;
            }
            $portalPatient = DB::table('patients')->where('user_id', $patientUser->id)->first();
            if (! $portalPatient) {
                throw new RuntimeException('La cuenta del paciente debe tener su ficha vinculada.');
            }

            $students = [
                ['Valeria Camila Mendoza Rojas', 'Enfermería · II-A', '2007-03-14', 'Rosa Rojas (madre)'],
                ['Diego Alejandro Torres Silva', 'Computación · IV-B', '2005-08-22', 'Carlos Torres (padre)'],
                ['Lucía Fernanda Castillo Vega', 'Administración · II-A', '2007-01-09', 'Ana Vega (madre)'],
                ['Mateo Sebastián Ramírez Flores', 'Contabilidad · VI-A', '2004-11-18', 'Julia Flores (madre)'],
                ['Camila Isabel Herrera Soto', 'Enfermería · IV-A', '2006-04-27', 'Luis Herrera (padre)'],
                ['Gabriel Andrés Vargas León', 'Computación · II-B', '2007-07-05', 'Patricia León (madre)'],
                ['Daniela Alejandra Ruiz Paredes', 'Administración · IV-A', '2005-12-12', 'Miguel Ruiz (padre)'],
                ['Joaquín Nicolás Salazar Cruz', 'Contabilidad · II-A', '2007-05-30', 'Elena Cruz (madre)'],
                ['Mariana Luciana Gutiérrez Díaz', 'Enfermería · VI-A', '2004-09-16', 'Sergio Gutiérrez (padre)'],
                ['Sebastián Adrián Cárdenas Peña', 'Computación · VI-B', '2004-06-03', 'Carmen Peña (madre)'],
            ];
            $patientIds = [];
            foreach ($students as $index => [$name, $group, $birth, $contact]) {
                $patientIds[] = $this->patient('DEMO-EST-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), [
                    'name' => $name, 'type' => 'student', 'birth_date' => $birth, 'group' => $group,
                    'emergency_contact' => $contact, 'phone' => null, 'emergency_phone' => null,
                    'allergies' => 'Sin información confirmada. Registro ficticio.',
                    'background' => 'Ficha ficticia de demostración del sistema Tópico. No corresponde a una persona real.',
                ]);
            }
            $teacherPatient = $this->patient('DEMO-DOC-001', ['name' => 'Patricia Elena Navarro Campos', 'type' => 'teacher', 'birth_date' => '1986-02-11', 'group' => 'Área de formación general', 'background' => 'Ficha ficticia de demostración.']);
            $staffPatient = $this->patient('DEMO-PER-001', ['name' => 'Ricardo Alberto Morales Ríos', 'type' => 'staff', 'birth_date' => '1989-10-24', 'group' => 'Administración institucional', 'background' => 'Ficha ficticia de demostración.']);

            $catalog = [
                ['Paracetamol', 'Tabletas 500 mg', 'tableta', 20, 80],
                ['Ibuprofeno', 'Tabletas 400 mg', 'tableta', 20, 10],
                ['Sales de rehidratación oral', 'Sobre individual', 'sobre', 10, 30],
                ['Solución salina', 'Frasco 100 ml', 'frasco', 8, 24],
                ['Alcohol medicinal', 'Frasco 250 ml', 'frasco', 5, 12],
                ['Clorhexidina', 'Solución 2% · frasco 100 ml', 'frasco', 5, 9],
                ['Loratadina', 'Tabletas 10 mg', 'tableta', 15, 40],
                ['Suero fisiológico', 'Frasco 500 ml', 'frasco', 5, 4],
            ];
            $batchIds = [];
            foreach ($catalog as $index => [$name, $presentation, $unit, $minimum, $quantity]) {
                $medicine = DB::table('medicines')->where('name', $name)->where('presentation', $presentation)->first();
                $medicineId = $medicine?->id ?? $this->insert('medicines', ['name' => $name, 'presentation' => $presentation, 'unit' => $unit, 'minimum_stock' => $minimum], now()->subDays(20));
                $batchIds[] = $this->batch($medicineId, 'DEMO-LOTE-'.($index + 1), $index === 6 ? today()->addDays(18) : today()->addMonths(9 + $index), $quantity, $coordinator->id);
            }
            $salineId = DB::table('medicine_batches')->where('id', $batchIds[3])->value('medicine_id');
            $this->batch($salineId, 'DEMO-PROXIMO', today()->addDays(12), 6, $coordinator->id);
            $this->batch($salineId, 'DEMO-VENCIDO', today()->subDays(3), 2, $coordinator->id);

            $reasons = ['Malestar durante la clase', 'Molestia en el tobillo después de actividad física', 'Dolor de cabeza referido por la estudiante', 'Cansancio durante la jornada académica', 'Raspadura superficial en el antebrazo', 'Molestia abdominal referida por el estudiante', 'Mareo al terminar una exposición', 'Irritación leve en la piel', 'Control de evolución de una atención previa', 'Molestia en la muñeca durante prácticas'];
            $consultationIds = [];
            foreach ($patientIds as $index => $patientId) {
                $at = now()->subDays(($index % 7) + 1)->subHours(1);
                $consultationIds[] = $this->caseRecord($patientId, $teacher->id, $staff->id, $reasons[$index], 'completed', $at, $index);
            }
            foreach (range(0, 3) as $index) {
                $consultationIds[] = $this->caseRecord($patientIds[$index], $teacher->id, $staff->id, $reasons[$index], 'in_progress', now()->subMinutes(70 - $index * 10), $index);
            }
            foreach (range(4, 6) as $index) {
                $this->caseRecord($patientIds[$index], $teacher->id, $staff->id, $reasons[$index], 'pending', now()->subMinutes(20 - $index), $index);
            }
            $this->caseRecord($patientIds[7], $teacher->id, $staff->id, 'Solicitud duplicada reportada desde el aula', 'cancelled', now()->subHours(2), 7);
            $portalCompleted = $this->caseRecord($portalPatient->id, $patientUser->id, $staff->id, 'Seguimiento de una atención anterior', 'completed', now()->subDays(2), 2);
            $portalActive = $this->caseRecord($portalPatient->id, $patientUser->id, $staff->id, 'Malestar durante la jornada de estudios', 'in_progress', now()->subMinutes(30), 1);
            $this->caseRecord($portalPatient->id, $patientUser->id, $staff->id, 'Solicitud de revisión de indicaciones', 'pending', now()->subMinutes(5), 3);
            $this->caseRecord($teacherPatient, $teacher->id, $staff->id, 'Cansancio al terminar la jornada docente', 'completed', now()->subDays(1), 4);
            $this->caseRecord($staffPatient, $coordinator->id, $staff->id, 'Molestia durante actividades de oficina', 'completed', now()->subMinutes(90), 5);

            foreach ([0, 2, 4, 6] as $index) {
                $this->delivery($consultationIds[$index], $batchIds[3], $staff->id);
            }
            $this->delivery($portalCompleted, $batchIds[3], $staff->id);
            $this->referral($consultationIds[1], $staff->id, 'Centro de Salud San Gabriel (ficticio)', 'Evaluación complementaria de la molestia referida en el tobillo.', true, now()->subDays(2));
            $this->referral($consultationIds[11], $staff->id, 'Centro de Salud San Gabriel (ficticio)', 'Se registra derivación para evaluación complementaria.', false, now()->subMinutes(45));
            $this->referral($portalCompleted, $staff->id, 'Policlínico Santa Rosa (ficticio)', 'Seguimiento externo documentado en este caso de ejemplo.', true, now()->subDays(1));
            $this->referral($portalActive, $staff->id, 'Centro de Salud San Gabriel (ficticio)', 'Evaluación complementaria; seguimiento pendiente.', false, now()->subMinutes(15));
            $this->insert('audit_logs', ['user_id' => $coordinator->id, 'action' => 'seed_demo_20261028', 'entity' => 'demo_dataset', 'entity_id' => 1], now());
            $this->command?->info('Carga completada: 10 estudiantes, 2 fichas de docente/personal, 23 solicitudes, 18 atenciones, 8 medicamentos, 10 lotes, 15 movimientos y 4 derivaciones. Las cuentas y datos anteriores se conservaron.');
        });
    }

    private function insert(string $table, array $data, Carbon $at): int
    {
        return DB::table($table)->insertGetId([...$data, 'created_at' => $at, 'updated_at' => $at]);
    }

    private function patient(string $document, array $data): int
    {
        if (DB::table('patients')->where('document', $document)->exists()) {
            throw new RuntimeException('Ya existe la ficha '.$document.'. La carga se canceló sin modificar datos.');
        }

        return $this->insert('patients', [...$data, 'document' => $document], now()->subDays(21));
    }

    private function batch(int $medicineId, string $lot, Carbon $expires, int $quantity, int $responsible): int
    {
        $id = $this->insert('medicine_batches', ['medicine_id' => $medicineId, 'lot' => $lot, 'expires_at' => $expires->toDateString(), 'quantity' => $quantity], now()->subDays(20));
        $this->insert('stock_movements', ['batch_id' => $id, 'user_id' => $responsible, 'type' => 'entry', 'quantity' => $quantity, 'instructions' => 'Ingreso de inventario ficticio para demostración.'], now()->subDays(20));

        return $id;
    }

    private function caseRecord(int $patientId, int $requester, int $responsible, string $reason, string $status, Carbon $at, int $variant): ?int
    {
        $request = $this->insert('care_requests', ['patient_id' => $patientId, 'requested_by' => $requester, 'reason' => $reason.' · Caso ficticio.', 'priority' => $variant === 6 ? 'urgent' : 'normal', 'status' => $status], $at);
        if (in_array($status, ['pending', 'cancelled'])) {
            return null;
        }
        $started = $at->copy()->addMinutes(2);
        $completed = $status === 'completed' ? $started->copy()->addMinutes(15) : null;
        $id = $this->insert('consultations', [
            'patient_id' => $patientId, 'request_id' => $request, 'attended_by' => $responsible,
            'reason' => $reason, 'symptoms' => 'El paciente refiere el malestar descrito. Caso simulado para mostrar el flujo de atención.',
            'temperature' => 36.4 + ($variant % 4) / 10, 'systolic' => 110 + ($variant % 4) * 2,
            'diastolic' => 70 + ($variant % 3) * 2, 'heart_rate' => 72 + $variant, 'saturation' => 98 + ($variant % 2),
            'weight' => 54 + $variant * 2, 'height' => 158 + $variant * 2,
            'observations' => 'Registro ficticio de demostración. Los valores y el relato no corresponden a una evaluación real.',
            'care' => 'Recepción del paciente, registro de signos vitales y observación en el tópico (caso simulado).',
            'instructions' => $status === 'completed' ? 'Indicaciones de ejemplo registradas al cierre. Consultar el seguimiento de la derivación, cuando corresponda.' : 'Atención de ejemplo en curso. Pendiente registrar la evolución y el cierre.',
            'status' => $status, 'completed_at' => $completed,
        ], $started);
        if ($completed) {
            DB::table('consultations')->where('id', $id)->update(['updated_at' => $completed]);
            DB::table('care_requests')->where('id', $request)->update(['updated_at' => $completed]);
        }
        $this->insert('audit_logs', ['user_id' => $responsible, 'action' => 'demo_create', 'entity' => 'consultation', 'entity_id' => $id], $started);

        return $id;
    }

    private function delivery(int $consultationId, int $batchId, int $responsible): void
    {
        $at = Carbon::parse(DB::table('consultations')->where('id', $consultationId)->value('created_at'))->addMinutes(6);
        $changed = DB::table('medicine_batches')->where('id', $batchId)->where('quantity', '>=', 1)->decrement('quantity', 1);
        if (! $changed) {
            throw new RuntimeException('No hay stock para completar los movimientos ficticios.');
        }
        $this->insert('stock_movements', ['batch_id' => $batchId, 'consultation_id' => $consultationId, 'user_id' => $responsible, 'type' => 'delivery', 'quantity' => 1, 'instructions' => 'Movimiento simulado de solución salina, vinculado a una atención ficticia. No constituye una indicación de uso.'], $at);
    }

    private function referral(int $consultationId, int $responsible, string $destination, string $reason, bool $closed, Carbon $at): void
    {
        $consultation = DB::table('consultations')->find($consultationId);
        $created = Carbon::parse($consultation->created_at)->addMinutes(5);
        $this->insert('referrals', ['consultation_id' => $consultationId, 'created_by' => $responsible, 'destination' => $destination, 'reason' => $reason.' Derivación ficticia.', 'priority' => 'normal', 'status' => $closed ? 'closed' : 'open', 'follow_up' => $closed ? 'En el caso simulado se recibió la constancia de atención y se cerró el seguimiento.' : 'Pendiente recepción de la constancia de atención del caso simulado.', 'closed_at' => $closed ? $at : null], $created);
    }
}
