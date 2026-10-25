<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Workdo\Account\Entities\Customer;
use Workdo\Account\Entities\CustomerClient;
use Illuminate\Support\Facades\DB;
use Workdo\Taskly\Entities\Task;


class HelpdeskTicketAjaxController extends Controller
{
    public function customerClients(Customer $customer)
    {

        $customerId = $customer->id ?? null;
        
        $isClientOrCustomer = \Laratrust::hasRole('Client') || \Laratrust::hasRole('Customer');
        
        if ($isClientOrCustomer) {
            // SOLO los CC asociados al usuario logueado vía customer_clients_users
            $clients = CustomerClient::query()
                ->from('customer_clients as cc')
                ->select('cc.id','cc.name')
                ->join('customer_clients_users as ccu', 'ccu.customer_client_id', '=', 'cc.id')
                ->when($customerId, fn($q) => $q->where('cc.customer_id', $customerId)) // opcional
                ->where('ccu.user_id', Auth::id())
                ->distinct()
                ->orderBy('cc.name')
                ->get();
        } else {
            // TODOS los CC (del customer indicado si se pasó $customerId)
            $clients = CustomerClient::query()
                ->from('customer_clients as cc')
                ->select('cc.id','cc.name')
                ->when($customerId, fn($q) => $q->where('cc.customer_id', $customerId)) // opcional
                ->orderBy('cc.name')
                ->get();
        }

        return response()->json($clients);
    }
    
    public function customersByTask(int $taskId)
    {
        // 1) obtener el project_id de la tarea
        $projectId = Task::whereKey($taskId)->value('project_id');
        if (!$projectId) {
            return response()->json([]); // sin proyecto -> lista vacía
        }
    
        // 2) client_ids asociados al proyecto (tabla pivot)
        // OJO: si tu tabla se llama "client_project", cámbiala aquí.
        $clientIds = DB::table('client_projects')
            ->where('project_id', $projectId)
            ->pluck('client_id')
            ->unique()
            ->values();
    
        if ($clientIds->isEmpty()) {
            return response()->json([]);
        }
    
        // 3) customers cuyo user_id esté en esos client_ids
        $customers = Customer::query()
            ->select('id','name')
            ->whereIn('user_id', $clientIds->all())
            ->orderBy('name')
            ->get();
    
        return response()->json($customers);
    }    
}
