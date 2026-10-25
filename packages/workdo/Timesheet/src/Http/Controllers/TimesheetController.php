<?php

namespace Workdo\Timesheet\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException; 
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Workdo\Timesheet\DataTables\TimesheetDataTable;
use Workdo\Timesheet\Entities\Timesheet;
use Workdo\Timesheet\Events\CreateTimesheet;
use Workdo\Timesheet\Events\DestroyTimesheet;
use Workdo\Timesheet\Events\UpdateTimesheet;
use Carbon\Carbon;

class TimesheetController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(TimesheetDataTable $dataTable)
    {
        $u = \Auth::user();
    
        // Permisos de acceso a la pantalla
        if (!($u->isAbleTo('timesheet manage') || \Laratrust::hasRole(['Customer','Client','Consultant']))) {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    
        $start       = Carbon::now()->startOfWeek(Carbon::SUNDAY);
        $fromDefault = $start->toDateString();
        $toDefault   = $start->copy()->addDays(6)->toDateString();
    
        $canSeeAllTimesheets = $this->canSeeAllTimesheets($u);

        if ($canSeeAllTimesheets) {
            $consultants = \App\Models\User::where('workspace_id', getActiveWorkSpace())
                ->orderBy('name')
                ->get(['id','name','email']);
        
            $clients = \Workdo\Account\Entities\CustomerClient::orderBy('name')
                ->get(['id','name']);
        
            return $dataTable->render('timesheet::timesheet.index', compact(
                'consultants',
                'clients',
                'fromDefault',
                'toDefault'
            ));
        }
        
        // ====== Usuario NO administrador: limitar combos a lo que le pertenece ======
        $userId = $u->id;
    
        // Helpers Collection
        $ccIdsDirect         = collect();
        $customerIds         = collect();
        $ccIdsViaCustomers   = collect();
        $projectIdsAsClient  = collect();
        $projectIdsAsMember  = collect();
        $projectIds          = collect();
        $ccIdsViaProjects    = collect();
    
        // --- CustomerClients asignados directamente ---
        if (\Schema::hasTable('customer_clients_users')) {
            $ccIdsDirect = \DB::table('customer_clients_users')
                ->where('user_id', $userId)
                ->pluck('customer_client_id');
        }
    
        // --- Customers asignados al usuario ---
        if (\Schema::hasTable('customer_users')) {
            $customerIds = \DB::table('customer_users')
                ->where('user_id', $userId)
                ->pluck('customer_id');
        }
    
        // --- CustomerClients de esos Customers ---
        if ($customerIds->isNotEmpty()) {
            $ccIdsViaCustomers = \DB::table('customer_clients')
                ->when(\Schema::hasColumn('customer_clients','workspace_id'), function ($q) {
                    $q->where('workspace_id', getActiveWorkSpace());
                })
                ->when(\Schema::hasColumn('customer_clients','created_by'), function ($q) {
                    $q->where('created_by', creatorId());
                })
                ->whereIn('customer_id', $customerIds)
                ->pluck('id');
        }
    
        // --- Proyectos donde el usuario es "cliente" (client_projects) ---
        if (\Schema::hasTable('client_projects')) {
            $projectIdsAsClient = \DB::table('client_projects')
                ->where('client_id', $userId)
                ->pluck('project_id');
        }
    
        // --- Proyectos donde el usuario es miembro (user_projects) ---
        if (\Schema::hasTable('user_projects')) {
            $projectIdsAsMember = \DB::table('user_projects')
                ->where('user_id', $userId)
                ->pluck('project_id');
        }
    
        // Merge y filtra a proyectos del workspace actual
        $projectIds = $projectIdsAsClient->merge($projectIdsAsMember)->unique()->values();
        if ($projectIds->isNotEmpty() && \Schema::hasTable('projects')) {
            $projectIds = \DB::table('projects')
                ->whereIn('id', $projectIds)
                ->when(\Schema::hasColumn('projects','workspace'), function ($q) {
                    $q->where('workspace', getActiveWorkSpace());
                })
                ->when(\Schema::hasColumn('projects','created_by'), function ($q) {
                    $q->where('created_by', creatorId());
                })
                ->pluck('id');
        }

        // --- Desde esos proyectos, obtener sus Customers y luego sus CustomerClients ---
        if ($projectIds->isNotEmpty() && \Schema::hasTable('client_projects')) {
            $clientUserIds = \DB::table('client_projects')
                ->whereIn('project_id', $projectIds)
                ->pluck('client_id');
    
            $customerIdsFromProjects = \DB::table('customers')
                ->when(\Schema::hasColumn('customers','workspace'), function ($q) {
                    $q->where('workspace', getActiveWorkSpace());
                })
                ->when(\Schema::hasColumn('customers','created_by'), function ($q) {
                    $q->where('created_by', creatorId());
                })
                ->whereIn('user_id', $clientUserIds)
                ->pluck('id');
    
            if ($customerIdsFromProjects->isNotEmpty()) {
                $ccIdsViaProjects = \DB::table('customer_clients')
                    ->when(\Schema::hasColumn('customer_clients','workspace_id'), function ($q) {
                        $q->where('workspace_id', getActiveWorkSpace());
                    })
                    ->when(\Schema::hasColumn('customer_clients','created_by'), function ($q) {
                        $q->where('created_by', creatorId());
                    })
                    ->whereIn('customer_id', $customerIdsFromProjects)
                    ->pluck('id');
            }
        }
    
        // ---- CLIENTS visibles (combo) ----
        $allowedClientIds = $ccIdsDirect->merge($ccIdsViaCustomers)->merge($ccIdsViaProjects)->unique()->values();
    
        $clients = $allowedClientIds->isEmpty()
        ? collect()
        : \Workdo\Account\Entities\CustomerClient::query()
            ->whereIn('id', $allowedClientIds->all())
            ->when(\Schema::hasColumn('customer_clients','workspace_id'), function ($q) {
                $q->where('workspace_id', getActiveWorkSpace());
            })
            ->when(\Schema::hasColumn('customer_clients','created_by'), function ($q) {
                $q->where('created_by', creatorId());
            })
            ->orderBy('name')
            ->get(['id','name']);
        
        // ---- CONSULTANTS visibles (combo) ----
        $consultantIds = collect();
    
        // 1) miembros de los proyectos visibles
        if ($projectIds->isNotEmpty() && \Schema::hasTable('user_projects')) {
            $consultantIds = \DB::table('user_projects')
                ->whereIn('project_id', $projectIds->all())
                ->pluck('user_id');
        }
    
        // 2) usuarios que han hecho TS sobre esos proyectos/clients visibles
        if ($projectIds->isNotEmpty() || $allowedClientIds->isNotEmpty()) {
            $tsUsers = \DB::table('timesheets')
                ->where('created_by', creatorId())
                ->where('workspace_id', getActiveWorkSpace())
                ->where(function ($w) use ($projectIds, $allowedClientIds) {
                    if ($projectIds->isNotEmpty()) {
                        $w->whereIn('project_id', $projectIds->all());
                    }
                    if ($allowedClientIds->isNotEmpty()) {
                        $w->orWhereIn('customer_client_id', $allowedClientIds->all());
                    }
                })
                ->distinct()
                ->pluck('user_id');
    
            $consultantIds = $consultantIds->merge($tsUsers);
        }
    
        // Incluye al usuario logueado por si no cayó en los sets anteriores
        if ($this->mustSeeOnlyOwnTimesheets($u)) {
            $consultants = \App\Models\User::where('id', $userId)
                ->where('workspace_id', getActiveWorkSpace())
                ->orderBy('name')
                ->get(['id','name','email']);
        } else {
            $consultantIds = $consultantIds->push($userId)->unique()->values();
        
            $consultants = $consultantIds->isEmpty()
                ? collect()
                : \App\Models\User::whereIn('id', $consultantIds->all())
                    ->where('workspace_id', getActiveWorkSpace())
                    ->orderBy('name')
                    ->get(['id','name','email']);
        }

        return $dataTable->render('timesheet::timesheet.index', compact('consultants','clients','fromDefault','toDefault'));
    }



    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        try{
        if (\Auth::user()->isAbleTo('timesheet create')) {
            $user = User::where('workspace_id', getActiveWorkSpace())->where('created_by', '=', creatorId())->emp()->get()->pluck('name', 'id');
            $project = '';
            if (module_is_active('Taskly')) {
                if (Auth::user()->type == 'company') {
                    $project = \Workdo\Taskly\Entities\Project::where('workspace', getActiveWorkSpace())->where('created_by', '=', creatorId())->projectonly()->get()->pluck('name', 'id');
                } else {
                    $userproject = \Workdo\Taskly\Entities\UserProject::where('user_id', '=', Auth::user()->id)->get()->pluck('project_id');
                    $project = \Workdo\Taskly\Entities\Project::whereIn('id', $userproject)->projectonly()->get()->pluck('name', 'id');
                }
            }
            $mytime = \Carbon\Carbon::now();
            $date = $mytime->toDateString();
            $customers = \Workdo\Account\Entities\Customer::where('workspace', getActiveWorkSpace())
                     ->where('created_by', creatorId())
                     ->orderBy('name')->pluck('name','id');

            $date = \Carbon\Carbon::now()->toDateString();
            if (!\Auth::user()->isAbleTo('timesheet create')) {
                return response()->json(['error' => __('Permission denied.')], 401);
            }
            $vars = $this->buildCreateVars();
            return view('timesheet::timesheet.create', $vars);
            //return view('timesheet::timesheet.create', compact('user','date','project','customers'));            

        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
            
        }
        catch (\Throwable $e) {
                 \Log::error('Error al crear timesheet', [
                    'msg'   => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'date'  => $request->input('date'),
                    'user'  => Auth::id(),
                ]);
        
                report($e);
        
                if ($request->ajax()) {
                    return response()->json(['ok'=>false,'message'=>__('Something went wrong.')], 500);
                }
                return back()->with('error', __('Something went wrong.'));
            }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('timesheet create')) {
            if ($request->ajax() || $request->boolean('popup')) {
                return response()->json(['ok' => false, 'message' => __('Permission denied.')], 403);
            }
            return back()->with('error', __('Permission denied.'));
        }
    
        try {
            $data = $this->validatedData($request);
            $data['ticket_id'] = $request->filled('ticket_id') ? (int) $request->ticket_id : null;
            $ts = Timesheet::create($data);
    
            if ($request->ajax() || $request->boolean('popup')) {
                return response()->json([
                    'ok'      => true,
                    'message' => __('Timesheet successfully created.'),
                    'id'      => $ts->id,
                ]);
            }
    
            return back()->with('success', __('Timesheet successfully created.'));
        } catch (ValidationException $e) {
            if ($request->ajax() || $request->boolean('popup')) {
                return response()->json([
                    'ok'     => false,
                    'errors' => $e->errors(),
                ], 422);
            }
    
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            // loguear por si acaso
            \Log::error('Timesheet store failed', [
                'userId' => Auth::id(),
                'msg'    => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);
    
            if ($request->ajax() || $request->boolean('popup')) {
                return response()->json([
                    'ok'      => false,
                    'message' => __('Something went wrong.'),
                ], 500);
            }
    
            return back()->with('error', __('Something went wrong.'));
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return redirect()->back()->with('error', 'Page not found.');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
// use Illuminate\Http\Request; (ya lo tienes)
public function edit(Request $request, Timesheet $timesheet)
{
    if (!\Auth::user()->isAbleTo('timesheet edit')) {
        return $request->ajax() || $request->boolean('popup')
            ? response()->json(['error' => __('Permission denied.')], 403)
            : redirect()->back()->with('error', __('Permission denied.'));
    }

    $user = User::where('workspace_id', getActiveWorkSpace())
                ->where('created_by', creatorId())->emp()->pluck('name', 'id');

    $project = module_is_active('Taskly')
        ? \Workdo\Taskly\Entities\Project::where('created_by', creatorId())
            ->where('workspace', getActiveWorkSpace())
            ->projectonly()->orderBy('name')->pluck('name', 'id')
        : collect();

    $task = $timesheet->project_id
        ? \Workdo\Taskly\Entities\Task::where('project_id', $timesheet->project_id)->pluck('title', 'id')
        : collect();

    $customers = \Workdo\Account\Entities\Customer::where('workspace', getActiveWorkSpace())
                    ->where('created_by', creatorId())
                    ->orderBy('name')->pluck('name','id');

    $prefilledTicket = null;
    if (!empty($timesheet->ticket_id)) {
        $prefilledTicket = \DB::table('helpdesk_tickets')
            ->select('id','number','subject')
            ->where('id', $timesheet->ticket_id)
            ->first();
    }

    if ($request->ajax() || $request->boolean('popup')) {
        return view('timesheet::timesheet.edit', compact('timesheet','user','project','task','customers','prefilledTicket'));
    }

    // Navegación directa (poco común): muestra la misma vista igual
    return view('timesheet::timesheet.edit', compact('timesheet','user','project','task','customers','prefilledTicket'));
}


    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
public function update(Request $request, $id)
{
    $ts = Timesheet::findOrFail($id);

    if (!Auth::user()->isAbleTo('timesheet edit')) {
        if ($request->ajax() || $request->boolean('popup')) {
            return response()->json(['ok'=>false,'message'=>__('Permission denied.')], 403);
        }
        return back()->with('error', __('Permission denied.'));
    }

    try {
        $data = $this->validatedData($request, $ts);
        $data['ticket_id'] = $request->filled('ticket_id') ? (int) $request->ticket_id : null;
        $ts->update($data);

        if ($request->ajax() || $request->boolean('popup')) {
            return response()->json([
                'ok'      => true,
                'message' => __('Timesheet successfully updated.'),
            ]);
        }

        return back()->with('success', __('Timesheet successfully updated.'));
    } catch (ValidationException $e) {
        if ($request->ajax() || $request->boolean('popup')) {
            return response()->json([
                'ok'     => false,
                'errors' => $e->errors(),
            ], 422);
        }
        return back()->withErrors($e->errors())->withInput();
    } catch (\Throwable $e) {
        \Log::error('Timesheet update failed', [
            'userId' => Auth::id(),
            'tsId'   => $id,
            'msg'    => $e->getMessage(),
        ]);

        if ($request->ajax() || $request->boolean('popup')) {
            return response()->json([
                'ok'      => false,
                'message' => __('Something went wrong.'),
            ], 500);
        }

        return back()->with('error', __('Something went wrong.'));
    }
}

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy(Timesheet $timesheet)
    {
        if (\Auth::user()->isAbleTo('timesheet delete')) {
            event(new DestroyTimesheet($timesheet));

            $timesheet->delete();

            return redirect()->back()->with('success', __('The timesheet has been deleted.'));
        } else {
            return redirect()->back()->with('error', 'permission Denied');
        }
    }


    public function gethours(Request $request, $id)
    {
        if ($request->user_id) {
            $user_id = $request->user_id;
        } else {
            $user_id = Auth::user()->id;
        }
        if (module_is_active('Hrm')) {
            $attendance = \Workdo\Hrm\Entities\Attendance::where('employee_id', '=', $user_id)->where('date', $request->date)->first();
            if (!empty($attendance)) {
                $start = strtotime($attendance->clock_in);
                $end = strtotime($attendance->clock_out);
                $total = $end - $start;
                $time = date("H:i", $total);

                $time = explode(":", $time);
                $timesheets = Timesheet::where('user_id', $user_id)->whereDate('date', $request->date)->where('type', 'clock in/clock out')->get();

                $timesheetshours = 0;
                $timesheetsminutes = 0;
                foreach ($timesheets as $value) {

                    $timesheetshours = $timesheetshours + $value->hours;
                    $timesheetsminutes = $timesheetsminutes + $value->minutes;
                }
                $hourss = $time[0] - $timesheetshours;
                $minutess = $time[1] - $timesheetsminutes;
                $time = Timesheet::find($id);
                $hours = $time->hours + $hourss;
                $minutes = $time->minutes + $minutess;
                $array = array($hours, $minutes);
                $hours = implode(":", $array);
            } else {
                $hours = date("H:i", 0);
            }
        } else {
            $hours = date("H:i", 0);
        }
        return response()->json($hours);
    }

    public function totalhours(Request $request)
    {
        $user_id = 0;
        if ($request->user_id != null) {
            $user_id = $request->user_id;
        } else {
            if (Auth::user() != null) {
                $user_id = Auth::user()->id;
            }
        }
        if (module_is_active('Hrm')) {
            $attendance = \Workdo\Hrm\Entities\Attendance::where('employee_id', '=', $user_id)->where('date', $request->date)->first();
            if (!empty($attendance)) {
                $start = strtotime($attendance->clock_in);
                $end = strtotime($attendance->clock_out);
                $total = $end - $start;
                $time = date("H:i", $total);
                $time = explode(":", $time);
                $timesheets = Timesheet::where('user_id', $user_id)->whereDate('date', $request->date)->where('type', 'clock in/clock out')->get();

                $timesheetshours = 0;
                $timesheetsminutes = 0;
                foreach ($timesheets as $value) {

                    $timesheetshours = $timesheetshours + $value->hours;
                    $timesheetsminutes = $timesheetsminutes + $value->minutes;
                }
                $hours = $time[0] - $timesheetshours;
                $minutes = $time[1] - $timesheetsminutes;
                $array = array($hours, $minutes);
                $hours = implode(":", $array);
            } else {
                $hours = date("H:i", '0');
            }
        } else {
            $hours = date("H:i", '0');
        }
        return response()->json($hours);
    }

    public function gettask(Request $request)
    {
        $task = \Workdo\Taskly\Entities\Task::where('project_id', $request->project_id)->get()->pluck('title', 'id');
        return response()->json($task);
    }
    
    private function roundUpToQuarterCapStr(string $hhmm): string
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));
        $m = (int) (ceil($m / 15) * 15);
        if ($m === 60) { $m = 0; $h++; }
        if ($h >= 24) { $h = 23; $m = 59; } // no permitir 24:00
        return sprintf('%02d:%02d', $h, $m);
    }

    private function roundUpToQuarter(string $hhmm): string {
        [$h,$m] = array_map('intval', explode(':', $hhmm));
        $m = (int) (ceil($m / 15) * 15);
        if ($m === 60) { $m = 0; $h = ($h + 1) % 24; }
        return sprintf('%02d:%02d', $h, $m);
    }

    private function roundUpToQuarterWrap(string $hhmm): string
    {
        [$h,$m] = array_map('intval', explode(':', $hhmm));
        $m = (int) (ceil($m/15) * 15);
        $h += intdiv($m, 60);
        $m  = $m % 60;
        $h  = $h % 24; // 24:00 -> 00:00 siguiente día
        return sprintf('%02d:%02d', $h, $m);
    }
    
    private function diffInHMOvernight(string $from, string $to): array
    {
        [$fh,$fm] = array_map('intval', explode(':', $from));
        [$th,$tm] = array_map('intval', explode(':', $to));
        $a = $fh*60 + $fm;
        $b = $th*60 + $tm;
        $d = $b - $a;
        if ($d < 0) $d += 24*60;         // cruza medianoche
        if ($d === 0) {
            throw ValidationException::withMessages([
                'from_time' => [__('The "From" time cannot be equal to the "To" time.')],
            ]);
        }
        return [intdiv($d,60), $d % 60];
    }
    
    private function diffInHM(string $from, string $to): array
    {
        [$fh,$fm] = array_map('intval', explode(':', $from));
        [$th,$tm] = array_map('intval', explode(':', $to));
        $a = $fh*60 + $fm; $b = $th*60 + $tm;
        if ($b < $a) {
            throw ValidationException::withMessages([
                'from_time' => [__('The "From" time cannot be greater than the "To" time.')],
            ]);
        }
        $d = $b - $a;
        return [intdiv($d,60), $d % 60];
    }
    
    private function parseHHMM(string $hhmm): array
    {
        if (!preg_match('/^\d{2}:\d{2}$/', $hhmm)) {
            throw ValidationException::withMessages([
                'duration' => [__('Invalid time format (hh:mm).')],
            ]);
        }
        [$h,$m] = array_map('intval', explode(':', $hhmm));
        if ($h === 0 && $m === 0) {
            throw ValidationException::withMessages([
                'duration' => [__('Time must be greater than 00:00.')],
            ]);
        }
        if ($h > 23 || $m > 59) {
            throw ValidationException::withMessages([
                'duration' => [__('Time must be between 00:01 and 23:59.')],
            ]);
        }
        return [$h,$m];
    }
    
    private function validatedData(Request $request, ?Timesheet $ts = null): array
    {
        $type = $request->input('type'); // 'Project' | 'Manually' | 'Clock in/Clock out'
    
        $rules = [
            'date'        => ['required','date','before_or_equal:today'],
            'description' => ['required','string','max:2000','min:3'],
            'notes'       => ['nullable','string'],
            'from_time'   => ['nullable','date_format:H:i'],
            'to_time'     => ['nullable','date_format:H:i'],
            'duration'    => ['nullable','regex:/^\d{2}:\d{2}$/'], // HH:MM
            'ticket_id'   => ['nullable', 'integer', 'exists:helpdesk_tickets,id'],
        ];
    
        if ($type === 'Project') {
            $rules['project_id']         = ['required','integer'];
            $rules['task_id']            = ['required','integer'];
            $rules['customer_client_id'] = ['required','integer'];
        } elseif ($type === 'Manually') {
            $rules['customer_id']        = ['required','integer'];
            $rules['customer_client_id'] = ['required','integer'];
        }
    
        $validator = Validator::make($request->all(), $rules);
    
        // ===== Validaciones cruzadas de tiempos (from/to vs duration) =====
        $validator->after(function ($v) use ($request) {
            $from = $request->input('from_time');
            $to   = $request->input('to_time');
            $dur  = $request->input('duration');
    
            // Debe venir (from y to) juntos o bien duration
            if (($from && !$to) || (!$from && $to)) {
                $v->errors()->add('from_time', __('Provide both From time and To time, or a Total (hh:mm).'));
                return;
            }
    
            if (empty($from) && empty($to)) {
                if (empty($dur)) {
                    $v->errors()->add('duration', __('Provide a Total (hh:mm) or a From/To time range.'));
                    return;
                }
                if (!preg_match('/^\d{2}:\d{2}$/', $dur)) {
                    $v->errors()->add('duration', __('Invalid time format (hh:mm).'));
                    return;
                }
                if ($dur === '00:00') {
                    $v->errors()->add('duration', __('Time must be greater than 00:00.'));
                    return;
                }
                // aquí no calculamos nada, solo validación básica
                return;
            }
    
            // Con rango: MISMO DÍA (To debe ser estrictamente mayor que From)
            $fromR = $this->roundUpToQuarterCapStr($from);
            $toR   = $this->roundUpToQuarterCapStr($to);
    
            [$fh,$fm] = array_map('intval', explode(':', $fromR));
            [$th,$tm] = array_map('intval', explode(':', $toR));
            $a = $fh*60 + $fm;
            $b = $th*60 + $tm;
    
            if ($b <= $a) {
                // NO se usa throw aquí, solo se añade al bag
                $v->errors()->add('from_time', __('Time range must be at least 1 minute.'));
            }
        });
    
        // ===== Ejecutar validación base (lanza ValidationException si algo falla) =====
        $data = $validator->validate();
    
        // ===== Regla de 3 días hábiles (después de la validación base) =====
        $u = \Auth::user();
    
        // BYPASS por rol
        $roleBypass = \Laratrust::hasRole(['super admin','Company','Staff','HR','Administrator'])
            || (isset($u->type) && in_array(strtolower($u->type), ['super admin','company','staff','hr','administrator']));
    
        // ¿CREATE o EDIT con cambio de fecha?
        $tsOriginalDate = $ts && $ts->date
            ? \Carbon\Carbon::parse($ts->date)->toDateString()
            : null;
        $requestedDate  = (string) $data['date'];
        $shouldCheck    = is_null($ts) || ($tsOriginalDate !== $requestedDate);
    
        if (!$roleBypass && $shouldCheck) {
            $tsDate = \Carbon\Carbon::parse($requestedDate)->startOfDay();
            $today  = \Carbon\Carbon::now()->startOfDay();
    
            if ($tsDate->lt($today)) {
                $isValidDate = $this->canUserFillTimesheet($u, $tsDate, $today);
    
                if (!$isValidDate) {
                    throw ValidationException::withMessages([
                        'date' => [__('You cannot submit timesheets for this date.')],
                    ]);
                }
            }
        }
    
        // ===== Completar datos base =====
        $data['user_id']      = Auth::id();
        $data['type']         = $type;
        $data['workspace_id'] = getActiveWorkSpace();
        $data['created_by']   = creatorId();
    
        // ===== Calcular hours/minutes =====
        $from = $request->input('from_time');
        $to   = $request->input('to_time');
        $dur  = $request->input('duration');
    
        if ($from && $to) {
            // Redondeo hacia arriba al próximo cuarto, con tope 23:59
            $fromR = $this->roundUpToQuarterCapStr($from);
            $toR   = $this->roundUpToQuarterCapStr($to);
    
            [$h,$m] = $this->diffInHM($fromR, $toR); // retorna diff positiva
            if ($h === 0 && $m === 0) {
                throw ValidationException::withMessages([
                    'from_time' => [__('Time range must be at least 1 minute.')],
                ]);
            }
    
            $data['hours']   = $h;
            $data['minutes'] = $m;
            $data['from_time'] = $fromR;
            $data['to_time']   = $toR;
    
            // --- Bloquea solapes si hay rango ---
            $userId = (int) $data['user_id'];
            $date   = $data['date'];
    
            $overlap = \Workdo\Timesheet\Entities\Timesheet::query()
                ->where('user_id', $userId)
                ->whereDate('date', $date)
                ->whereNotNull('from_time')
                ->whereNotNull('to_time')
                ->when($ts, fn($q) => $q->where('id', '<>', $ts->id))
                // Condición de solape clásico: A.from < B.to && A.to > B.from
                ->where(function ($q) use ($fromR, $toR) {
                    $q->where('from_time', '<', $toR)
                      ->where('to_time',   '>', $fromR);
                })
                ->exists();
    
            if ($overlap) {
                throw ValidationException::withMessages([
                    'from_time' => [__('This time range overlaps another timesheet for the same user and date.')],
                    'to_time'   => [__('This time range overlaps another timesheet for the same user and date.')],
                ]);
            }
    
        } else {
            // Solo duration: validar y redondear al próximo cuarto (tope 23:59)
            [$h,$m]  = $this->parseHHMM($dur); // valida 00:01..23:59
            $durR    = $this->roundUpToQuarterCapStr(sprintf('%02d:%02d', $h, $m));
            [$h2,$m2]= array_map('intval', explode(':', $durR));
            if ($h2 === 0 && $m2 === 0) {
                throw ValidationException::withMessages([
                    'duration' => [__('Time must be greater than 00:00.')],
                ]);
            }
            $data['hours']   = $h2;
            $data['minutes'] = $m2;
        }
    
        return $data;
    }


    public function getCustomerClientsByProject(Request $request)
    {
        $projectId = (int) $request->input('project_id');
        if (!$projectId) {
            return response()->json([]);
        }
    
        // 1) project_id -> client_id (tabla puente client_projects)
        $clientId = DB::table('client_projects')
            ->where('project_id', $projectId)
            ->value('client_id');
    
        if (!$clientId) {
            return response()->json([]); // no hay cliente asociado al proyecto
        }
    
        // 2) client_id -> customer_id (en tabla customers)
        $customerQuery = DB::table('customers');
    
        if (Schema::hasColumn('customers', 'workspace')) {
            $customerQuery->where('workspace', getActiveWorkSpace());
        }
        if (Schema::hasColumn('customers', 'created_by')) {
            $customerQuery->where('created_by', creatorId());
        }
        if (Schema::hasColumn('customers', 'user_id')) {
            $customerQuery->where('user_id', $clientId);
        }
    
        $customerId = $customerQuery->value('id');
    
        if (!$customerId) {
            return response()->json([]); // no hay customer vinculable
        }
    
        // 3) customer_id -> listado de customer_clients
        $clientsQuery = DB::table('customer_clients')->where('customer_id', $customerId);
        if (Schema::hasColumn('customer_clients', 'workspace_id')) {
            $clientsQuery->where('workspace_id', getActiveWorkSpace());
        }
        if (Schema::hasColumn('customer_clients', 'created_by')) {
            $clientsQuery->where('created_by', creatorId());
        }
    
        $clients = $clientsQuery->orderBy('name')->pluck('name', 'id');
    
        return response()->json($clients);
    }    

    public function getCustomerClientsByCustomer(Request $request) {
        $customerId = (int) $request->input('customer_id');
        $clients = \Workdo\Account\Entities\CustomerClient::where('customer_id',$customerId)->pluck('name','id');
        return response()->json($clients);
    }

    public function bulkValidate(Request $request)
    {
        $u = Auth::user();
        $can = $u->hasRole('super admin')
            || $u->isAbleTo('timesheet validate')   // con espacio
            || $u->isAbleTo('timesheet-validate');  // con guión
    
        if (!$can) {
            return response()->json(['ok'=>false,'message'=>__('Permission denied.')], 403);
        }
    
        $ids = array_filter((array) $request->input('ids', []));
        if (empty($ids)) {
            return response()->json(['ok'=>false,'message'=>__('No rows selected.')], 422);
        }
        
        $affected = Timesheet::whereIn('id', $ids)
            ->where('validated', false)
            ->where('billed', false)
            ->where(function($q){ $q->whereNull('invoiced')->orWhere('invoiced', false); })
            ->where('paid', false)
            ->update(['validated' => true]);
        return response()->json([
            'ok' => true,
            'message' => "Approved {$affected} timesheets.",
        ]);
    }
    
    public function bulkUnvalidate(Request $request)
    {
        $u = Auth::user();
        $can = $u->hasRole('super admin')
            || $u->isAbleTo('timesheet unvalidate')
            || $u->isAbleTo('timesheet-unvalidate')
            || $u->isAbleTo('timesheet validate')    // si usas mismo permiso para revertir
            || $u->isAbleTo('timesheet-validate');
    
        if (!$can) {
            return response()->json(['ok'=>false,'message'=>__('Permission denied.')], 403);
        }
    
        $ids = array_filter((array) $request->input('ids', []));
        if (empty($ids)) {
            return response()->json(['ok'=>false,'message'=>__('No rows selected.')], 422);
        }
    
        $affected = Timesheet::whereIn('id', $ids)
            ->where('validated', true)
            ->where('billed', false)
            ->where(function($q){ $q->whereNull('invoiced')->orWhere('invoiced', false); })
            ->where('paid', false)
            ->update(['validated' => false]);
    
        return response()->json(['ok'=>true,'message'=>__('Unapproved :n timesheets.', ['n'=>$affected])]);
    }
    
public function ticketsForTimesheet(Request $r)
{
    $type     = $r->get('type'); // 'Clock in/Clock out' | 'Project' | 'Service Wise'
    $project  = $r->get('project_id');
    $task     = $r->get('task_id');
    $client   = $r->get('customer_client_id'); // customer client id
    $customer = $r->get('customer_id');        // si lo usas

    // 1) Detectar la mejor columna para el # del ticket
    $idCol = Schema::hasColumn('helpdesk_tickets', 'ticket_id')
        ? 'ticket_id'
        : (Schema::hasColumn('helpdesk_tickets', 'number')
            ? 'number'
            : 'id');

    // 2) Armar label seguro (sin problemas de NULL ni de encoding)
    $labelExpr = \DB::raw("CONCAT('#', t.`$idCol`, ' - ', LEFT(IFNULL(t.subject,''), 120)) as label");

    $q = \DB::table('helpdesk_tickets as t')
        ->select('t.id', $labelExpr)
        ->whereNotIn('t.status', ['closed','Closed','CLOSED']);

    // a) Clock in/Clock out wise -> tickets sin task/project NI customer/client
    if ($type === 'Clock in/Clock out') {
        $q->whereNull('t.task_id')
          ->whereNull('t.customer_client_id')
          ->whereNull('t.customer_id');
    }

    // b) Project wise -> tickets con task/project (si envías uno u otro)
    if ($type === 'Project') {
        $q->where(function ($w) use ($project, $task) {
            if ($task)    { $w->where('t.task_id', $task); }
        });
    }

    // c) Service wise -> SIN task/project PERO CON customer/client
    if ($type === 'Service Wise') {
        $q->whereNull('t.task_id')->whereNull('t.project_id')
          ->where(function ($w) use ($client, $customer) {
              if ($client)   { $w->where('t.customer_client_id', $client); }
              if ($customer) { $w->orWhere('t.customer_id', $customer); }
          });
    }

    // opcional: limitar a workspace actual si existe la columna
    if (Schema::hasColumn('helpdesk_tickets', 'workspace_id') && function_exists('getActiveWorkSpace')) {
        $q->where('t.workspace_id', getActiveWorkSpace());
    }

    $items = $q->orderByDesc('t.id')->limit(100)->get();

    return response()->json([
        'ok'    => true,
        'items' => $items->map(fn ($x) => ['id' => $x->id, 'text' => $x->label])->values(),
    ]);
}
public function createFromTicket(Request $request, int $ticketId)
{
    $u = \Auth::user();
    if (!($u->hasRole('super admin') || $u->isAbleTo('timesheet create'))) {
        abort(403, __('Permission denied.'));
    }

    $t = \DB::table('helpdesk_tickets')->where('id', $ticketId)->first();
    if (!$t) abort(404);

    $projectId  = data_get($t, 'project_id');
    $taskId     = data_get($t, 'task_id');
    $clientId   = data_get($t, 'client_id');
    $customerId = data_get($t, 'customer_id');

    $type = ($taskId || $projectId) ? 'Project'
          : (($clientId || $customerId) ? 'Service Wise' : 'Clock in/Clock out');

    $prefill = [
        'type'        => $type,
        'project_id'  => $projectId,
        'task_id'     => $taskId,
        'client_id'   => $clientId,
        'customer_id' => $customerId,
        'ticket_id'   => $t->id,
        'user_id'     => $u->id,
        'lock'        => true,
    ];

    // Evita caracteres raros en algunos settings: usa guion normal
    $ticketNumber  = data_get($t, 'number', $t->id);
    $ticketSubject = (string) data_get($t, 'subject', '');
    $ticketLabel   = '#'.$ticketNumber.' - '.Str::limit($ticketSubject, 120);

    $vars = $this->buildCreateVars() + [
        'prefilledTicket' => $t,
        'ticketLabel'     => $ticketLabel,
        'prefill'         => $prefill,
    ];

    // Para AJAX/popup devolvemos sólo el contenido del formulario (sin layout)
    if ($request->ajax() || $request->boolean('popup')) {
        return view('timesheet::timesheet._form', $vars + compact('prefill','ticketLabel'));
    }

    // Si alguien entra directo por URL, igual mostramos el formulario “plano”
    return view('timesheet::timesheet.create', $vars + compact('prefill','ticketLabel'));
}
    private function buildCreateVars(): array
    {
        $u = \Auth::user();
    
        // fecha por defecto
        $date = Carbon::now()->toDateString();
    
        // proyectos (igual que en create())
        $project = [];
        if (module_is_active('Taskly')) {
            if ($u->type === 'company') {
                $project = \Workdo\Taskly\Entities\Project::where('workspace', getActiveWorkSpace())
                    ->where('created_by', creatorId())
                    ->projectonly()->orderBy('name')->pluck('name','id');
            } else {
                $userproject = \Workdo\Taskly\Entities\UserProject::where('user_id', $u->id)
                    ->pluck('project_id');
                $project = \Workdo\Taskly\Entities\Project::whereIn('id', $userproject)
                    ->projectonly()->orderBy('name')->pluck('name','id');
            }
        }
    
        // customers (para "Service Wise")
        $customers = \Workdo\Account\Entities\Customer::where('workspace', getActiveWorkSpace())
            ->where('created_by', creatorId())
            ->orderBy('name')->pluck('name','id');
    
        return compact('date','project','customers');
    }
    
    public function view(Request $request, $id)
    {
        $ts = Timesheet::with(['project','customerClient','user'])->findOrFail($id);
    
        $user    = Auth::user();
        $authId  = (int) Auth::id();
        $wsId    = function_exists('getActiveWorkSpace') ? getActiveWorkSpace() : null;
        $ownerId = function_exists('creatorId') ? (int) creatorId() : $authId;
    
        $isOwner = ((int)$ts->user_id === $authId) || ((int)$ts->created_by === $ownerId);
        $isSuper = \Laratrust::hasRole('super admin')
                || (\Laratrust::hasRole('super admin', $wsId))
                || (isset($user->type) && strtolower($user->type) === 'super admin');
        $canView = \Laratrust::hasPermission(['timesheet view','timesheet show','timesheet manage'])
                || \Laratrust::hasPermission(['timesheet view','timesheet show','timesheet manage'], $wsId);
    
        if (!($isOwner || $isSuper || $canView)) {
            abort(403, 'Unauthorized');
        }
    
        // SIEMPRE la misma vista; la vista hará el “modo popup” sola
        $isPopup = $request->ajax() || $request->boolean('popup');
        return view('timesheet::timesheet.view', compact('ts','isPopup'));
    }

    private function canUserFillTimesheet($user, \Carbon\Carbon $date, \Carbon\Carbon $today)
    {
        // normalizamos a inicio de día
        $workDate = $date->copy()->startOfDay();
        $now      = \Carbon\Carbon::now()->startOfDay();
    
        // ahora sí, pasamos Carbon a businessDaysDiff
        $biz = $this->businessDaysDiff($workDate, $today);
    
        $daysBack    = (int) config('timesheets.allowed_days_back', 3);
        $daysForward = (int) config('timesheets.allowed_days_forward', 0);
    
        $minDate = $now->copy()->subDays($daysBack)->startOfDay();
        $maxDate = $now->copy()->addDays($daysForward)->endOfDay();
    
        $withinNormalWindow = $workDate->between($minDate, $maxDate) || $biz <= $daysBack;
    
        // override por usuario
        $hasOverride =
            $user->timesheet_override_until &&
            $user->timesheet_override_until->isFuture();
    
        return $withinNormalWindow || $hasOverride;
    }

    // Cuenta días hábiles (Lun–Vie) desde $from (inclusive) hasta el día anterior a $to (exclusivo).
    private function businessDaysDiff(\Carbon\Carbon $from, \Carbon\Carbon $to): int
    {
        if ($from->gt($to)) [$from, $to] = [$to, $from];
        $days = 0;
        $cursor = $from->copy()->startOfDay();
        $end    = $to->copy()->startOfDay(); // no contamos hoy
    
        while ($cursor->lt($end)) {
            if (!$cursor->isWeekend()) { $days++; }
            $cursor->addDay();
        }
        return $days;
    }

    private function canSeeAllTimesheets(User $u): bool
    {
        $type = strtolower(trim((string) ($u->type ?? '')));
    
        return
            \Laratrust::hasRole('super admin')
            || \Laratrust::hasRole('company')
            || \Laratrust::hasRole('Company')
            || \Laratrust::hasRole('staff')
            || \Laratrust::hasRole('Staff')
            || $type === 'staff';
    }
    
    private function mustSeeOnlyOwnTimesheets(User $u): bool
    {
        return !$this->canSeeAllTimesheets($u);
    }    
    
}
