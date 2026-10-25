<?php

namespace Workdo\Timesheet\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Workdo\Timesheet\Entities\TimesheetInboundMailbox;
use Workdo\Timesheet\Entities\TimesheetInboundSenderMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingsController extends Controller
{
    /**
     * Retorna la vista parcial que se inyecta en la página global
     * de Settings de la empresa (/company/settings).
     *
     * El sistema llama a este método vía CompanySettingListener →
     * CompanySettingEvent, igual que hace Taskly con sus mailboxes.
     */
    public function index($settings = null)
    {
        $mailboxes  = TimesheetInboundMailbox::orderBy('name')->get();

        $senderMaps = TimesheetInboundSenderMap::with(['mailbox', 'employee'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Empleados del workspace actual
        $employees = \App\Models\User::where('workspace_id', getActiveWorkSpace())
            ->where('created_by', creatorId())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Proyectos (si Taskly está activo)
        $projects = collect();
        if (module_is_active('Taskly') && Schema::hasTable('projects')) {
            $projects = \Workdo\Taskly\Entities\Project::where('workspace', getActiveWorkSpace())
                ->where('created_by', creatorId())
                ->projectonly()
                ->orderBy('name')
                ->pluck('name', 'id');
        }

        // Tareas
        $tasks = collect();
        if (module_is_active('Taskly') && Schema::hasTable('tasks')) {
            $tasks = \Workdo\Taskly\Entities\Task::whereHas('project', function ($q) {
                $q->where('workspace', getActiveWorkSpace())
                  ->where('created_by', creatorId());
            })->orderBy('title')->pluck('title', 'id');
        }

        // Customers
        $customers = collect();
        if (Schema::hasTable('customers')) {
            $customers = \Workdo\Account\Entities\Customer::where('workspace', getActiveWorkSpace())
                ->where('created_by', creatorId())
                ->orderBy('name')
                ->pluck('name', 'id');
        }

        // Customer clients
        $customerClients = collect();
        if (Schema::hasTable('customer_clients')) {
            $customerClients = \Workdo\Account\Entities\CustomerClient::orderBy('name')
                ->pluck('name', 'id');
        }

        // Log de los últimos 50 correos procesados
        $processedLog = DB::table('timesheet_inbound_processed')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('timesheet::company.settings.inbound_mailboxes', compact(
            'mailboxes',
            'senderMaps',
            'employees',
            'projects',
            'tasks',
            'customers',
            'customerClients',
            'processedLog'
        ));
    }
}
