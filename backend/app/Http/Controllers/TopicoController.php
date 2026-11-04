<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TopicoController extends Controller
{
    private function team(Request $request, bool $coordinator = false): void
    {
        abort_unless(in_array($request->user()->role, $coordinator ? ['coordinator'] : ['coordinator', 'staff']), 403, 'No tienes permiso para esta operación.');
    }

    private function validateData(Request $request, array $rules): array
    {
        return $request->validate($rules, [
            'required' => 'El campo :attribute es obligatorio.', 'unique' => 'El valor de :attribute ya está registrado.',
            'exists' => 'El registro de :attribute no existe.', 'in' => 'El valor de :attribute no es válido.',
            'min' => 'El campo :attribute debe tener un mínimo de :min.', 'max' => 'El campo :attribute supera el máximo de :max.',
            'email' => 'Ingresa un correo válido.', 'integer' => 'El campo :attribute debe ser un entero.',
            'numeric' => 'El campo :attribute debe ser numérico.', 'date' => 'Ingresa una fecha válida en :attribute.',
            'after' => 'La fecha :attribute debe ser posterior a :date.', 'before_or_equal' => 'La fecha :attribute no puede ser posterior a :date.',
        ]);
    }

    private function audit(Request $request, string $action, string $entity, int $id): void
    {
        DB::table('audit_logs')->insert(['user_id' => $request->user()->id, 'action' => $action, 'entity' => $entity, 'entity_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function scoped(Request $request, Builder $query, string $patientColumn, ?string $requesterColumn = null): Builder
    {
        if ($request->user()->role === 'patient') {
            $query->where($patientColumn, DB::table('patients')->where('user_id', $request->user()->id)->value('id') ?? 0);
        } elseif ($request->user()->role === 'teacher') {
            if ($requesterColumn) {
                $query->where($requesterColumn, $request->user()->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public function login(Request $request): JsonResponse
    {
        $data = $this->validateData($request, ['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt([...$data, 'active' => true])) {
            throw ValidationException::withMessages(['email' => 'Correo o contraseña incorrectos, o cuenta desactivada.']);
        }
        $request->session()->regenerate();

        return response()->json($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function patients(Request $request): JsonResponse
    {
        $query = DB::table('patients')->orderBy('name');
        if ($request->user()->role === 'teacher') {
            return response()->json($query->where('type', 'student')->get(['id', 'name', 'group']));
        }

        return response()->json($this->scoped($request, $query, 'id')->get());
    }

    public function savePatient(Request $request, ?int $id = null): JsonResponse
    {
        $this->team($request);
        if ($id) {
            abort_unless(DB::table('patients')->where('id', $id)->exists(), 404);
        }
        $data = $this->validateData($request, [
            'name' => 'required|string|max:180', 'document' => ['required', 'string', 'max:30', Rule::unique('patients')->ignore($id)],
            'type' => 'required|in:student,teacher,staff', 'birth_date' => 'nullable|date|before_or_equal:today',
            'group' => 'nullable|string|max:100', 'phone' => 'nullable|string|max:30',
            'emergency_contact' => 'nullable|string|max:180', 'emergency_phone' => 'nullable|string|max:30',
            'allergies' => 'nullable|string|max:3000', 'background' => 'nullable|string|max:5000',
        ]);
        $id = DB::transaction(function () use ($request, $id, $data) {
            if ($id) {
                DB::table('patients')->where('id', $id)->update([...$data, 'updated_at' => now()]);
            } else {
                $id = $this->insert('patients', $data);
            }
            $this->audit($request, 'save', 'patient', $id);

            return $id;
        });

        return response()->json(['id' => $id]);
    }

    public function patientHistory(Request $request, int $id): JsonResponse
    {
        $this->team($request);
        $patient = DB::table('patients')->find($id);
        abort_unless($patient, 404);

        return response()->json(['patient' => $patient, 'consultations' => DB::table('consultations')->where('patient_id', $id)->orderByDesc('id')->get()]);
    }

    private function requestQuery(Request $request): Builder
    {
        return $this->scoped($request, DB::table('care_requests as r')->join('patients as p', 'p.id', '=', 'r.patient_id')->join('users as u', 'u.id', '=', 'r.requested_by')->select('r.*', 'p.name as patient_name', 'p.type as patient_type', 'u.name as requested_by_name'), 'r.patient_id', 'r.requested_by');
    }

    public function requests(Request $request): JsonResponse
    {
        return response()->json($this->requestQuery($request)->orderByDesc('r.id')->get());
    }

    public function saveRequest(Request $request): JsonResponse
    {
        $data = $this->validateData($request, ['patient_id' => 'required|integer|exists:patients,id', 'reason' => 'required|string|max:3000', 'priority' => 'required|in:normal,urgent']);
        if ($request->user()->role === 'patient') {
            abort_unless(DB::table('patients')->where('id', $data['patient_id'])->where('user_id', $request->user()->id)->exists(), 403);
        }
        if ($request->user()->role === 'teacher') {
            abort_unless(DB::table('patients')->where('id', $data['patient_id'])->where('type', 'student')->exists(), 403);
        }
        $id = DB::transaction(function () use ($request, $data) {
            $id = $this->insert('care_requests', [...$data, 'requested_by' => $request->user()->id, 'status' => 'pending']);
            $this->audit($request, 'create', 'request', $id);

            return $id;
        });

        return response()->json(['id' => $id], 201);
    }

    public function cancelRequest(Request $request, int $id): JsonResponse
    {
        DB::transaction(function () use ($request, $id) {
            $record = $this->requestQuery($request)->where('r.id', $id)->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_unless($record->status === 'pending', 422, 'Solo puedes cancelar solicitudes pendientes.');
            DB::table('care_requests')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            $this->audit($request, 'cancel', 'request', $id);
        });

        return response()->json(['message' => 'Solicitud cancelada.']);
    }

    public function startRequest(Request $request, int $id): JsonResponse
    {
        $this->team($request);
        $consultationId = DB::transaction(function () use ($request, $id) {
            $record = DB::table('care_requests')->where('id', $id)->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_unless($record->status === 'pending', 422, 'La solicitud ya fue atendida o cancelada.');
            $consultationId = $this->insert('consultations', ['patient_id' => $record->patient_id, 'request_id' => $id, 'reason' => $record->reason, 'attended_by' => $request->user()->id, 'status' => 'in_progress']);
            DB::table('care_requests')->where('id', $id)->update(['status' => 'in_progress', 'updated_at' => now()]);
            $this->audit($request, 'start', 'consultation', $consultationId);

            return $consultationId;
        });

        return response()->json(['id' => $consultationId], 201);
    }

    private function consultationQuery(Request $request): Builder
    {
        return $this->scoped($request, DB::table('consultations as c')->join('patients as p', 'p.id', '=', 'c.patient_id')->join('users as u', 'u.id', '=', 'c.attended_by')->select('c.*', 'p.name as patient_name', 'p.type as patient_type', 'p.document', 'u.name as attended_by_name'), 'c.patient_id');
    }

    public function consultations(Request $request): JsonResponse
    {
        abort_if($request->user()->role === 'teacher', 403);

        return response()->json($this->consultationQuery($request)->orderByDesc('c.id')->get());
    }

    public function consultation(Request $request, int $id): JsonResponse
    {
        $record = $this->consultationQuery($request)->where('c.id', $id)->first();
        abort_unless($record, 404);
        $record->deliveries = DB::table('stock_movements as s')->join('medicine_batches as b', 'b.id', '=', 's.batch_id')->join('medicines as m', 'm.id', '=', 'b.medicine_id')->where('s.consultation_id', $id)->select('s.*', 'm.name', 'm.unit', 'b.lot')->get();
        $record->referrals = DB::table('referrals')->where('consultation_id', $id)->get();
        if (in_array($request->user()->role, ['staff', 'coordinator'])) {
            $record->patient = DB::table('patients')->find($record->patient_id);
        }

        return response()->json($record);
    }

    public function saveConsultation(Request $request, ?int $id = null): JsonResponse
    {
        $this->team($request);
        $data = $this->validateData($request, [
            'patient_id' => 'required|integer|exists:patients,id', 'reason' => 'required|string|max:3000',
            'symptoms' => 'nullable|string|max:5000', 'observations' => 'nullable|string|max:5000',
            'care' => 'nullable|string|max:5000', 'instructions' => 'nullable|string|max:5000',
            'temperature' => 'nullable|numeric|between:20,50', 'systolic' => 'nullable|integer|between:30,300',
            'diastolic' => 'nullable|integer|between:10,200', 'heart_rate' => 'nullable|integer|between:10,300',
            'saturation' => 'nullable|integer|between:1,100', 'weight' => 'nullable|numeric|between:0.1,500',
            'height' => 'nullable|numeric|between:10,250', 'status' => 'required|in:in_progress,completed',
        ]);
        if ($data['status'] === 'completed' && empty($data['care']) && empty($data['instructions'])) {
            throw ValidationException::withMessages(['care' => 'Registra los cuidados o las indicaciones antes de finalizar.']);
        }
        $id = DB::transaction(function () use ($request, $id, $data) {
            $record = $id ? DB::table('consultations')->where('id', $id)->lockForUpdate()->first() : null;
            if ($id) {
                abort_unless($record, 404);
                abort_unless($record->status === 'in_progress', 422, 'La atención finalizada ya no se puede modificar.');
                abort_unless((int) $record->patient_id === (int) $data['patient_id'], 422, 'No se puede cambiar el paciente de una atención.');
            }
            $data['completed_at'] = $data['status'] === 'completed' ? now() : null;
            if ($id) {
                DB::table('consultations')->where('id', $id)->update([...$data, 'updated_at' => now()]);
                if ($record->request_id) {
                    DB::table('care_requests')->where('id', $record->request_id)->update(['status' => $data['status'], 'updated_at' => now()]);
                }
            } else {
                $id = $this->insert('consultations', [...$data, 'attended_by' => $request->user()->id]);
            }
            $this->audit($request, $data['status'] === 'completed' ? 'complete' : 'save', 'consultation', $id);

            return $id;
        });

        return response()->json(['id' => $id]);
    }

    private function medicineRows(): Collection
    {
        $medicines = DB::table('medicines')->orderBy('name')->get();
        $batches = DB::table('medicine_batches')->orderBy('expires_at')->get()->groupBy('medicine_id');
        foreach ($medicines as $medicine) {
            $medicine->batches = $batches->get($medicine->id, collect());
            $medicine->stock = $medicine->batches->filter(fn ($batch) => $batch->expires_at > today()->toDateString())->sum('quantity');
            $medicine->total_stock = $medicine->batches->sum('quantity');
            $medicine->low_stock = $medicine->stock <= $medicine->minimum_stock;
        }

        return $medicines;
    }

    public function medicines(Request $request): JsonResponse
    {
        $this->team($request);

        return response()->json($this->medicineRows());
    }

    public function saveMedicine(Request $request, ?int $id = null): JsonResponse
    {
        $this->team($request);
        if ($id) {
            abort_unless(DB::table('medicines')->where('id', $id)->exists(), 404);
        }
        $data = $this->validateData($request, ['name' => ['required', 'string', 'max:180', Rule::unique('medicines')->where('presentation', $request->input('presentation'))->ignore($id)], 'presentation' => 'required|string|max:180', 'unit' => 'required|string|max:40', 'minimum_stock' => 'required|integer|min:0|max:1000000']);
        $id = DB::transaction(function () use ($request, $id, $data) {
            if ($id) {
                DB::table('medicines')->where('id', $id)->update([...$data, 'updated_at' => now()]);
            } else {
                $id = $this->insert('medicines', $data);
            }
            $this->audit($request, 'save', 'medicine', $id);

            return $id;
        });

        return response()->json(['id' => $id]);
    }

    public function stockEntry(Request $request): JsonResponse
    {
        $this->team($request);
        $data = $this->validateData($request, ['medicine_id' => 'required|integer|exists:medicines,id', 'lot' => 'required|string|max:80', 'expires_at' => 'required|date|after:today', 'quantity' => 'required|integer|min:1|max:1000000', 'instructions' => 'nullable|string|max:1000']);
        DB::transaction(function () use ($request, $data) {
            DB::table('medicines')->where('id', $data['medicine_id'])->lockForUpdate()->first();
            $batch = DB::table('medicine_batches')->where('medicine_id', $data['medicine_id'])->where('lot', $data['lot'])->lockForUpdate()->first();
            if ($batch) {
                abort_unless($batch->expires_at === $data['expires_at'], 422, 'El lote ya existe con otra fecha de vencimiento.');
                DB::table('medicine_batches')->where('id', $batch->id)->increment('quantity', (int) $data['quantity'], ['updated_at' => now()]);
                $batchId = $batch->id;
            } else {
                $batchId = $this->insert('medicine_batches', collect($data)->only(['medicine_id', 'lot', 'expires_at', 'quantity'])->all());
            }
            $id = $this->insert('stock_movements', ['batch_id' => $batchId, 'user_id' => $request->user()->id, 'type' => 'entry', 'quantity' => $data['quantity'], 'instructions' => $data['instructions'] ?? null]);
            $this->audit($request, 'entry', 'stock_movement', $id);
        });

        return response()->json(['message' => 'Ingreso registrado.'], 201);
    }

    public function deliver(Request $request, int $id): JsonResponse
    {
        $this->team($request);
        $data = $this->validateData($request, ['batch_id' => 'required|integer|exists:medicine_batches,id', 'quantity' => 'required|integer|min:1|max:1000000', 'instructions' => 'required|string|max:3000']);
        DB::transaction(function () use ($request, $id, $data) {
            $consultation = DB::table('consultations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($consultation, 404);
            abort_unless($consultation->status === 'in_progress', 422, 'Solo puedes entregar medicamentos durante una atención abierta.');
            $batch = DB::table('medicine_batches')->where('id', $data['batch_id'])->lockForUpdate()->first();
            abort_unless($batch->expires_at > today()->toDateString(), 422, 'El lote está vencido o vence hoy.');
            $changed = DB::table('medicine_batches')->where('id', $batch->id)->where('quantity', '>=', $data['quantity'])->decrement('quantity', (int) $data['quantity'], ['updated_at' => now()]);
            abort_unless($changed, 422, 'No hay suficientes unidades en este lote.');
            $movement = $this->insert('stock_movements', [...$data, 'consultation_id' => $id, 'user_id' => $request->user()->id, 'type' => 'delivery']);
            $this->audit($request, 'deliver', 'stock_movement', $movement);
        });

        return response()->json(['message' => 'Entrega registrada.'], 201);
    }

    public function movements(Request $request): JsonResponse
    {
        $this->team($request);

        return response()->json(DB::table('stock_movements as s')->join('medicine_batches as b', 'b.id', '=', 's.batch_id')->join('medicines as m', 'm.id', '=', 'b.medicine_id')->join('users as u', 'u.id', '=', 's.user_id')->select('s.*', 'm.name', 'b.lot', 'u.name as responsible')->orderByDesc('s.id')->get());
    }

    private function referralQuery(Request $request): Builder
    {
        return $this->scoped($request, DB::table('referrals as r')->join('consultations as c', 'c.id', '=', 'r.consultation_id')->join('patients as p', 'p.id', '=', 'c.patient_id')->join('users as u', 'u.id', '=', 'r.created_by')->select('r.*', 'p.name as patient_name', 'p.document', 'u.name as responsible'), 'c.patient_id');
    }

    public function referrals(Request $request): JsonResponse
    {
        abort_if($request->user()->role === 'teacher', 403);

        return response()->json($this->referralQuery($request)->orderByDesc('r.id')->get());
    }

    public function saveReferral(Request $request, ?int $id = null): JsonResponse
    {
        $this->team($request);
        $data = $this->validateData($request, $id ? ['status' => 'required|in:open,closed', 'follow_up' => 'required|string|max:5000'] : ['consultation_id' => 'required|integer|exists:consultations,id', 'destination' => 'required|string|max:200', 'reason' => 'required|string|max:5000', 'priority' => 'required|in:normal,urgent']);
        $id = DB::transaction(function () use ($request, $id, $data) {
            if ($id) {
                $record = DB::table('referrals')->where('id', $id)->lockForUpdate()->first();
                abort_unless($record, 404);
                abort_unless($record->status === 'open', 422, 'La derivación ya fue cerrada.');
                DB::table('referrals')->where('id', $id)->update([...$data, 'closed_at' => $data['status'] === 'closed' ? now() : null, 'updated_at' => now()]);
            } else {
                $consultation = DB::table('consultations')->where('id', $data['consultation_id'])->lockForUpdate()->first();
                abort_unless($consultation && $consultation->status === 'in_progress', 422, 'La derivación debe registrarse desde una atención abierta.');
                $id = $this->insert('referrals', [...$data, 'created_by' => $request->user()->id]);
            }
            $this->audit($request, 'save', 'referral', $id);

            return $id;
        });

        return response()->json(['id' => $id]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $consultations = $this->consultationQuery($request);
        $requests = $this->requestQuery($request);
        $alerts = [];
        if (in_array($request->user()->role, ['staff', 'coordinator'])) {
            foreach ($this->medicineRows() as $medicine) {
                if ($medicine->low_stock) {
                    $alerts[] = ['key' => 's'.$medicine->id, 'type' => 'stock', 'name' => $medicine->name, 'message' => 'Stock disponible: '.$medicine->stock.' '.$medicine->unit.'. Mínimo: '.$medicine->minimum_stock.'.'];
                }
                foreach ($medicine->batches as $batch) {
                    if ($batch->quantity > 0 && $batch->expires_at <= today()->addDays(30)->toDateString()) {
                        $alerts[] = ['key' => 'b'.$batch->id, 'type' => 'expiry', 'name' => $medicine->name.' · '.$batch->lot, 'message' => ($batch->expires_at <= today()->toDateString() ? 'No disponible. Vencimiento: ' : 'Próximo a vencer: ').$batch->expires_at];
                    }
                }
            }
        }

        return response()->json([
            'today' => $request->user()->role === 'teacher'
                ? (clone $requests)->whereDate('r.created_at', today())->count()
                : (clone $consultations)->whereDate('c.created_at', today())->count(),
            'active' => $request->user()->role === 'teacher'
                ? (clone $requests)->where('r.status', 'in_progress')->count()
                : (clone $consultations)->where('c.status', 'in_progress')->count(),
            'completed' => (clone $requests)->where('r.status', 'completed')->count(),
            'pending' => (clone $requests)->where('r.status', 'pending')->count(),
            'referrals' => $this->referralQuery($request)->where('r.status', 'open')->count(),
            'requests' => $requests->where('r.status', 'pending')->orderByRaw("CASE WHEN r.priority = 'urgent' THEN 0 ELSE 1 END")->orderBy('r.created_at')->limit(6)->get(),
            'alerts' => $alerts,
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $this->team($request, true);
        $data = $this->validateData($request, ['from' => 'required|date', 'to' => 'required|date|after_or_equal:from', 'type' => 'nullable|in:student,teacher,staff']);
        $query = $this->consultationQuery($request)->whereDate('c.created_at', '>=', $data['from'])->whereDate('c.created_at', '<=', $data['to']);
        if (! empty($data['type'])) {
            $query->where('p.type', $data['type']);
        }
        $rows = $query->orderByDesc('c.id')->get();
        $ids = $rows->pluck('id');
        $deliveries = DB::table('stock_movements as s')->join('medicine_batches as b', 'b.id', '=', 's.batch_id')->join('medicines as m', 'm.id', '=', 'b.medicine_id')->whereIn('s.consultation_id', $ids)->select('m.name', 'm.unit', DB::raw('SUM(s.quantity) as quantity'))->groupBy('m.id', 'm.name', 'm.unit')->get();

        return response()->json(['rows' => $rows, 'total' => $rows->count(), 'completed' => $rows->where('status', 'completed')->count(), 'referrals' => DB::table('referrals')->whereIn('consultation_id', $ids)->count(), 'deliveries' => $deliveries]);
    }

    public function users(Request $request): JsonResponse
    {
        $this->team($request, true);

        return response()->json(User::orderBy('name')->get()->map(function ($user) {
            $user->patient_id = DB::table('patients')->where('user_id', $user->id)->value('id');

            return $user;
        }));
    }

    public function saveUser(Request $request, ?int $id = null): JsonResponse
    {
        $this->team($request, true);
        $data = $this->validateData($request, [
            'name' => 'required|string|max:180', 'email' => ['required', 'email', 'max:180', Rule::unique('users')->ignore($id)],
            'password' => ($id ? 'nullable' : 'required').'|string|min:10|max:128',
            'role' => 'required|in:coordinator,staff,teacher,patient', 'active' => 'required|boolean',
            'patient_id' => ['nullable', 'required_if:role,patient', 'integer', 'exists:patients,id'],
        ]);
        if ($data['role'] !== 'patient') {
            $data['patient_id'] = null;
        }
        DB::transaction(function () use ($request, $id, $data) {
            $user = $id ? User::findOrFail($id) : new User;
            abort_if($id === $request->user()->id && ($data['role'] !== 'coordinator' || ! $data['active']), 422, 'No puedes quitarte el acceso de coordinación.');
            if (! empty($data['patient_id'])) {
                $patient = DB::table('patients')->where('id', $data['patient_id'])->lockForUpdate()->first();
                abort_if($patient->user_id && (int) $patient->user_id !== $id, 422, 'El paciente ya tiene una cuenta vinculada.');
            }
            $attributes = collect($data)->except(['patient_id'])->all();
            if (empty($attributes['password'])) {
                unset($attributes['password']);
            }
            $user->fill($attributes)->save();
            DB::table('patients')->where('user_id', $user->id)->update(['user_id' => null]);
            if (! empty($data['patient_id'])) {
                DB::table('patients')->where('id', $data['patient_id'])->update(['user_id' => $user->id]);
            }
            $this->audit($request, 'save', 'user', $user->id);
        });

        return response()->json(['message' => 'Usuario guardado.']);
    }
}
