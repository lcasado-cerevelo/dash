<?php

namespace Workdo\Account\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;
use Workdo\Account\Entities\Customer;
use Workdo\Account\Entities\CustomerClient;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Workdo\Account\DataTables\CustomerDataTable;
use Workdo\Account\Events\CreateCustomer;
use Workdo\Account\Events\DestroyCustomer;
use Workdo\Account\Events\UpdateCustomer;
use Workdo\Taskly\Entities\TicketInboundMailbox;
use Workdo\Taskly\Entities\TicketInboundProjectMap;
use Workdo\Taskly\Entities\Project;
use App\Models\HelpdeskTicketCategory;
use App\Models\CustomerClientAssigned;


class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(CustomerDataTable $dataTable)
    {
        if (Auth::user()->isAbleTo('customer manage'))
        {
            return $dataTable->render('account::customer.index');
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        if (\Auth::user()->isAbleTo('customer create'))
        {
            if (module_is_active('CustomField')) {
                $customFields = \Workdo\CustomField\Entities\CustomField::where('workspace_id', getActiveWorkSpace())
                    ->where('module', 'Account')
                    ->where('sub_module', 'Customer')
                    ->get();
            } else {
                $customFields = null;
            }
    
            $workspaceId = function_exists('getActiveWorkSpace') ? getActiveWorkSpace() : null;
            $canSelectAcrossWorkspaces = \Auth::user()->isAbleTo('workspace select');
            
            $assignableUsers = User::query()
                ->select('id', 'name')
                ->when(!$canSelectAcrossWorkspaces && $workspaceId, function ($q) use ($workspaceId) {
                    $q->where('active_workspace', $workspaceId);
                })
                ->orderBy('name')
                ->get();
    
            return view('account::customer.create', compact('customFields', 'assignableUsers'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
    
    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        if (Auth::user()->isAbleTo('customer create'))
        {
            $canUse =  PlanCheck('User', Auth::user()->id);
            if ($canUse == false) {
                return redirect()->back()->with('error', 'You have maxed out the total number of customer allowed on your current plan');
            }
            $rules = [
                'name' => 'required',
                'contact' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/',
                'billing_name' => 'required',
                'billing_phone' => 'required',
                'billing_address' => 'required',
                'billing_city' => 'required',
                'billing_state' => 'required',
                'billing_country' => 'required',
                'billing_zip' => 'required',

                // Custom invoice/government fields
                'is_goverment' => 'sometimes|boolean',
                'invoice_abrv' => 'nullable|string|max:4',
                'certification_text' => 'nullable|string',
                'invoice_att' => 'nullable|string|max:250',
            ];

            $validator = \Validator::make($request->all(), $rules);
            if(empty($request->user_id))
            {
                $rules = [
                    'email' => ['required',
                                        Rule::unique('users')->where(function ($query) {
                                        return $query->where('created_by', creatorId())->where('workspace_id',getActiveWorkSpace());
                                    })
                        ],
                    'password' => 'required',
                    'contact' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/',

                    // Custom invoice/government fields
                    'is_goverment' => 'sometimes|boolean',
                    'invoice_abrv' => 'nullable|string|max:4',
                    'certification_text' => 'nullable|string',
                    'invoice_att' => 'nullable|string|max:250',
                ];
                $validator = \Validator::make($request->all(), $rules);
            }

            if ($validator->fails())
            {
                $messages = $validator->getMessageBag();
                return redirect()->route('customer.index')->with('error', $messages->first());
            }

            $roles = Role::where('name','client')->where('guard_name','web')->where('created_by',creatorId())->first();
            if(empty($roles))
            {
                return redirect()->back()->with('error', __('Cilent Role Not found!'));
            }
            if(!empty($request->user_id))
            {
                $user = User::find($request->user_id);

                if(empty($user))
                {
                    return redirect()->back()->with('error', __('Something went wrong please try again.'));
                }
                if($user->name != $request->name)
                {
                    $user->name = $request->name;
                    $user->save();
                }
                if($user->mobile_no != $request->contact)
                {
                    $user->mobile_no = $request->contact;
                    $user->save();
                }
            }
            else
            {
                $user = User::create(
                    [
                        'name' => $request['name'],
                        'email' => $request['email'],
                        'mobile_no' => $request['contact'],
                        'password' => Hash::make($request['password']),
                        'email_verified_at' => date('Y-m-d h:i:s'),
                        'type' => $roles->name,
                        'lang' => 'en',
                        'workspace_id' => getActiveWorkSpace(),
                        'active_workspace' =>getActiveWorkSpace(),
                        'created_by' => creatorId(),
                    ]
                    );
                    $user->save();
                    $user->addRole($roles);
            }
            $customer                  = new Customer();
            $customer->user_id         = $user->id;
            $customer->customer_id     = $this->customerNumber();
            $customer->name            = !empty($request->name) ? $request->name : null;
            $customer->contact         = !empty($request->contact) ? $request->contact : null;
            $customer->email           = !empty($user->email) ? $user->email : null;
            $customer->tax_number      = !empty($request->tax_number) ? $request->tax_number : null;
            $customer->password        = null;
            $customer->billing_name    = !empty($request->billing_name) ? $request->billing_name : null;
            $customer->billing_country = !empty($request->billing_country) ? $request->billing_country : null;
            $customer->billing_state   = !empty($request->billing_state) ? $request->billing_state : null;
            $customer->billing_city    = !empty($request->billing_city) ? $request->billing_city : null;
            $customer->billing_phone   = !empty($request->billing_phone) ? $request->billing_phone : null;
            $customer->billing_zip     = !empty($request->billing_zip) ? $request->billing_zip : null;
            $customer->billing_address = !empty($request->billing_address) ? $request->billing_address : null;

            $customer->shipping_name    = !empty($request->shipping_name) ? $request->shipping_name : null;
            $customer->shipping_country = !empty($request->shipping_country) ? $request->shipping_country : null;
            $customer->shipping_state   = !empty($request->shipping_state) ? $request->shipping_state : null;
            $customer->shipping_city    = !empty($request->shipping_city) ? $request->shipping_city : null;
            $customer->shipping_phone   = !empty($request->shipping_phone) ? $request->shipping_phone : null;
            $customer->shipping_zip     = !empty($request->shipping_zip) ? $request->shipping_zip : null;
            $customer->shipping_address = !empty($request->shipping_address) ? $request->shipping_address : null;
            $customer->lang             = !empty($user->lang) ? $user->lang : '';

            // Custom invoice/government fields
            $customer->is_goverment = $request->boolean('is_goverment');
            $customer->invoice_abrv = !empty($request->invoice_abrv) ? strtoupper(trim($request->invoice_abrv)) : null;
            $customer->certification_text = $request->input('certification_text');
            $customer->invoice_att = $request->input('invoice_att');

            $customer->workspace        = getActiveWorkSpace();
            $customer->created_by      = creatorId();
            $customer->save();
            $customer->users()->syncWithoutDetaching([$user->id]);
            if ($request->has('customer_clients')) {
                $incoming = collect($request->input('customer_clients', []))
                    ->map(function ($r) {
                        foreach ($r as $k => $v) {
                            if (is_string($v)) {
                                $r[$k] = trim($v);
                            }
                        }
                        return $r;
                    })
                    ->filter(function ($r) {
                        return !empty($r['name']);
                    })
                    ->values();
            
                $now = now();
                $keptIds = [];
            
                foreach ($incoming as $r) {
                    $attrs = Arr::only($r, [
                        'external_code','name','email','contact','country','state','city','phone','zip','address','notes',
                    ]);
            
                    if (!empty($r['id'])) {
                        // No debería ocurrir en create, pero por uniformidad:
                        $client = $customer->customerClients()->whereKey($r['id'])->first();
                        if ($client) {
                            $client->fill($attrs)->save();
                            $keptIds[] = $client->id;
                        }
                    } else {
                        $client = $customer->customerClients()->create($attrs + ['created_at'=>$now,'updated_at'=>$now]);
                        $keptIds[] = $client->id;
                        
                    }
                    
                    $assignedUserIds = collect($r['assigned_user_ids'] ?? [])
                        ->map(fn($id) => (int) $id)
                        ->filter(fn($id) => $id > 0)
                        ->unique()
                        ->values()
                        ->all();
                    
                    CustomerClientAssigned::where('customer_client_id', $client->id)->delete();
                    
                    if (!empty($assignedUserIds)) {
                        $rows = collect($assignedUserIds)->map(function ($uid) use ($client, $now) {
                            return [
                                'customer_client_id' => $client->id,
                                'user_id' => $uid,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        })->all();
                    
                        CustomerClientAssigned::insert($rows);
                    }                    
                }
            
                // Por consistencia con update(): si vino la sección, hacemos cleanup
                if (!empty($keptIds)) {
                    $customer->customerClients()->whereNotIn('id', $keptIds)->delete();
                } else {
                    $customer->customerClients()->delete();
                }
            }            

            if(module_is_active('CustomField'))
            {
                \Workdo\CustomField\Entities\CustomField::saveData($customer, $request->customField);
            }
            event(new CreateCustomer($request,$customer));

            return redirect()->back()->with('success', __('The customer has been created successfully.'));

        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function search(Request $request)
    {
        $q     = trim((string) $request->input('q', ''));
        $limit = (int) $request->input('limit', 20) ?: 20;
    
        $query = Customer::query()
            ->where('workspace', getActiveWorkSpace())
            ->where('created_by', creatorId());
    
        // (Opcional) Precargar por id para Select2 cuando ya hay un valor seleccionado
        if ($request->filled('id')) {
            $row = (clone $query)->whereKey($request->input('id'))
                ->first(['id','name','email']);
    
            if (!$row) {
                return response()->json([]);
            }
    
            return response()->json([[
                'id'   => $row->id,
                'text' => $row->email ? "{$row->name} ({$row->email})" : $row->name,
            ]]);
        }
    
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('contact', 'like', "%{$q}%");
            });
        }
    
        $items = $query->orderBy('name')
                       ->limit($limit)
                       ->get(['id','name','email']);
    
        $results = $items->map(function ($c) {
            return [
                'id'   => $c->id,
                'text' => $c->email ? "{$c->name} ({$c->email})" : $c->name,
            ];
        })->values();
    
        return response()->json($results);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(Request $request, $e_id)
    {
        if (Auth::user()->isAbleTo('customer show'))
        {
            try{
                $id       = \Crypt::decrypt($e_id);
            }catch(\Exception $e){
                return redirect()->back()->with('error','Customer Not Found');
            }
            $customer = Customer::where('user_id',$id)->first();
            if (!$customer) {
                $customer = Customer::whereHas('users', fn($q) => $q->where('users.id', $id))->first();
            }
            if(module_is_active('CustomField')){
                $customer->customField = \Workdo\CustomField\Entities\CustomField::getData($customer, 'Account','Customer');
                $customFields             = \Workdo\CustomField\Entities\CustomField::where('workspace_id', '=', getActiveWorkSpace())->where('module', '=', 'Account')->where('sub_module','Customer','customFields')->get();
            }else{
                $customFields = null;
            }
            $invoice   = Invoice::where('created_by', '=', creatorId())->where('workspace',getActiveWorkSpace())->where('customer_id', '=', $customer->id)->get()->pluck('id');

            $invoice_payment=InvoicePayment::whereIn('invoice_id',$invoice);

            $data['from_date']  = date('Y-m-1');
            $data['until_date'] = date('Y-m-t');
            $company_settings = getCompanyAllSetting();
            $settings['company_address'] = isset($company_settings['company_address']) ? $company_settings['company_address'] : '';
            $settings['company_city'] = isset($company_settings['company_city']) ? $company_settings['company_city'] : '';
            $settings['company_state'] = isset($company_settings['company_state']) ? $company_settings['company_state'] : '';
            $settings['company_zipcode'] = isset($company_settings['company_zipcode']) ? $company_settings['company_zipcode'] : '';
            $settings['company_country'] = isset($company_settings['company_country']) ? $company_settings['company_country'] : '';
            $invoice_payment->whereBetween('date',  [$data['from_date'], $data['until_date']]);
            $invoice_payment = $invoice_payment->get();
            
            $customerClients = $customer ? $customer->customerClients()->orderBy('name')->get() : collect();
$inboundProjectMaps = TicketInboundProjectMap::with(['mailbox', 'project', 'category', 'customerClient'])
    ->where('customer_id', $customer->id)
    ->orderBy('sort_order')
    ->get();
    
return view('account::customer.show', compact(
    'customer',
    'customFields',
    'invoice_payment',
    'data',
    'settings',
    'customerClients',
    'inboundProjectMaps'
));    
            //return view('account::customer.show', compact('customer','customFields','invoice_payment','data','settings','customerClients'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        if (Auth::user()->isAbleTo('customer edit'))
        {
            $user         = User::where('id',$id)->where('workspace_id',getActiveWorkSpace())->first();
            $customer     = Customer::where('user_id',$id)->where('workspace',getActiveWorkSpace())->first();
            if (!$customer) {
                $customer = Customer::whereHas('users', fn($q) => $q->where('users.id', $id))->first();
            }
             
            if(!empty($customer)){

                if(module_is_active('CustomField')){
                    $customer->customField = \Workdo\CustomField\Entities\CustomField::getData($customer, 'Account','Customer');
                    $customFields             = \Workdo\CustomField\Entities\CustomField::where('workspace_id', '=', getActiveWorkSpace())->where('module', '=', 'Account')->where('sub_module','Customer')->get();
                }else{
                    $customFields = null;
                }
            }else{
                if(module_is_active('CustomField')){
                    $customFields =  \Workdo\CustomField\Entities\CustomField::where('workspace_id',getActiveWorkSpace())->where('module', '=', 'Account')->where('sub_module','Customer')->get();
                }else{
                    $customFields = null;
                }
            }
            
            $mailboxes = TicketInboundMailbox::orderBy('name')->get();
            $projects = Project::orderBy('name')->get();
            $categories = HelpdeskTicketCategory::orderBy('name')->get();
            //$customerClients = !empty($customer) ? $customer->customerClients()->orderBy('name')->get() : collect();

            $customerClients = !empty($customer)
                ? $customer->customerClients->map(function ($client) {
                    $client->assigned_user_id = CustomerClientAssigned::where('customer_client_id', $client->id)
                        ->value('user_id');
            
                    return $client;
                })
                : collect();

            $inboundProjectMaps = !empty($customer)
                ? TicketInboundProjectMap::where('customer_id', $customer->id)->orderBy('sort_order')->get()
                : collect();

            $workspaceId = function_exists('getActiveWorkSpace') ? getActiveWorkSpace() : null;
            $canSelectAcrossWorkspaces = \Auth::user()->isAbleTo('workspace select');
            
            $assignableUsers = User::query()
                ->select('id', 'name')
                ->when(!$canSelectAcrossWorkspaces && $workspaceId, function ($q) use ($workspaceId) {
                    $q->where('active_workspace', $workspaceId);
                })
                ->orderBy('name')
                ->get();
                
            $assignUsers = $assignableUsers;
            return view('account::customer.edit', compact(
                'customer',
                'user',
                'customFields',
                'mailboxes',
                'projects',
                'categories',
                'assignUsers',
                'customerClients',
                'inboundProjectMaps',
                'assignableUsers'
            ));    

        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        if (Auth::user()->isAbleTo('customer edit'))
        {
            $rules = [
                'name' => 'required',
                'contact' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/',
                'billing_name' => 'required',
                'billing_phone' => 'required',
                'billing_address' => 'required',
                'billing_city' => 'required',
                'billing_state' => 'required',
                'billing_country' => 'required',
                'billing_zip' => 'required',

                // Custom invoice/government fields
                'is_goverment' => 'sometimes|boolean',
                'invoice_abrv' => 'nullable|string|max:4',
                'certification_text' => 'nullable|string',
                'invoice_att' => 'nullable|string|max:250',
            ];

            $validator = \Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }
            $user = User::where('id',$request->user_id)->first();
            if(empty($user))
            {
                return redirect()->back()->with('error', __('Something went wrong please try again.'));
            }
            if($user->name != $request->name)
            {
                $user->name = $request->name;
                $user->save();
            }
            if($user->mobile_no != $request->contact)
            {
                $user->mobile_no = $request->contact;
                $user->save();
            }

            $customer                   = Customer::find($id);
            $customer->name             = $request->name;
            $customer->contact          = $request->contact;
            $customer->tax_number       = $request->tax_number;
            $customer->billing_name     = $request->billing_name;
            $customer->billing_country  = $request->billing_country;
            $customer->billing_state    = $request->billing_state;
            $customer->billing_city     = $request->billing_city;
            $customer->billing_phone    = $request->billing_phone;
            $customer->billing_zip      = $request->billing_zip;
            $customer->billing_address  = $request->billing_address;
            $customer->shipping_name    = $request->shipping_name;
            $customer->shipping_country = $request->shipping_country;
            $customer->shipping_state   = $request->shipping_state;
            $customer->shipping_city    = $request->shipping_city;
            $customer->shipping_phone   = $request->shipping_phone;
            $customer->shipping_zip     = $request->shipping_zip;
            $customer->shipping_address = $request->shipping_address;

            // Custom invoice/government fields
            $customer->is_goverment = $request->boolean('is_goverment');
            $customer->invoice_abrv = !empty($request->invoice_abrv) ? strtoupper(trim($request->invoice_abrv)) : null;
            $customer->certification_text = $request->input('certification_text');
            $customer->invoice_att = $request->input('invoice_att');
            $customer->save();

            if ($request->has('inbound_project_maps')) {
                $incomingMaps = collect($request->input('inbound_project_maps', []))
                    ->map(function ($r) {
                        foreach ($r as $k => $v) {
                            if (is_string($v)) {
                                $r[$k] = trim($v);
                            }
                        }
                        return $r;
                    })
                    ->filter(function ($r) {
                        return !empty($r['match_value']) || !empty($r['project_id']) || !empty($r['mailbox_id']);
                    })
                    ->values();
            
                $keptMapIds = [];
            
                foreach ($incomingMaps as $r) {
                    $attrs = [
                        'mailbox_id' => !empty($r['mailbox_id']) ? $r['mailbox_id'] : null,
                        'match_type' => !empty($r['match_type']) ? $r['match_type'] : 'email',
                        'match_value' => !empty($r['match_value']) ? strtolower($r['match_value']) : null,
                        'match_name' => !empty($r['match_name']) ? $r['match_name'] : null,
                        'customer_id' => $customer->id,
                        'customer_client_id' => !empty($r['customer_client_id']) ? $r['customer_client_id'] : null,
                        'task_id' => !empty($r['task_id']) ? $r['task_id'] : null,
                        'project_id' => !empty($r['project_id']) ? $r['project_id'] : null,
                        'workspace_id' => getActiveWorkSpace(),
                        'category_id' => !empty($r['category_id']) ? $r['category_id'] : null,
                        'priority' => !empty($r['priority']) ? $r['priority'] : null,
                        'assign_to' => !empty($r['assign_to']) ? (string) $r['assign_to'] : null,
                        'sort_order' => isset($r['sort_order']) ? (int)$r['sort_order'] : 100,
                        'is_active' => !empty($r['is_active']) ? 1 : 0,
                    ];
            
                    if (!empty($r['id'])) {
                        $map = TicketInboundProjectMap::where('customer_id', $customer->id)->whereKey($r['id'])->first();
                        if ($map) {
                            $map->update($attrs);
                            $keptMapIds[] = $map->id;
                        }
                    } else {
                        $map = TicketInboundProjectMap::create($attrs);
                        $keptMapIds[] = $map->id;
                    }
                }
            
                if (!empty($keptMapIds)) {
                    TicketInboundProjectMap::where('customer_id', $customer->id)
                        ->whereNotIn('id', $keptMapIds)
                        ->delete();
                } else {
                    TicketInboundProjectMap::where('customer_id', $customer->id)->delete();
                }
            }

            if ($request->has('customer_clients')) {
                $incoming = collect($request->input('customer_clients', []))
                    ->map(function ($r) { foreach ($r as $k => $v) if (is_string($v)) $r[$k] = trim($v); return $r; })
                    ->filter(fn ($r) => !empty($r['name']))
                    ->values();

                $now = now();
                $keptIds = [];

                foreach ($incoming as $r) {
                    $attrs = Arr::only($r, [
                        'external_code','name','email','contact','country','state','city','phone','zip','address','notes',
                    ]);

                    if (!empty($r['id'])) {
                        $client = $customer->customerClients()->whereKey($r['id'])->first();
                        if ($client) { $client->fill($attrs)->save(); $keptIds[] = $client->id; }
                    } else {
                        $client = $customer->customerClients()->create($attrs + ['created_at'=>$now,'updated_at'=>$now]);
                        $keptIds[] = $client->id;
                    }
                    
                    $assignedUserId = !empty($r['assigned_user_id']) ? (int) $r['assigned_user_id'] : null;
                    
                    CustomerClientAssigned::where('customer_client_id', $client->id)->delete();
                    
                    if (!empty($assignedUserId)) {
                        CustomerClientAssigned::create([
                            'customer_client_id' => $client->id,
                            'user_id' => $assignedUserId,
                        ]);
                    }
                    
                }

                // Borra solo si la sección estuvo presente
                if (!empty($keptIds)) {
                    $customer->customerClients()->whereNotIn('id', $keptIds)->delete();
                } else {
                    $customer->customerClients()->delete();
                }
            }
            
            if(module_is_active('CustomField'))
            {
                \Workdo\CustomField\Entities\CustomField::saveData($customer, $request->customField);
            }
            event(new UpdateCustomer($request,$customer));

            return redirect()->back()->with('success', __('The customer details are updated successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $customer = Customer::where('workspace',getActiveWorkSpace())->where('user_id',$id)->first();

        if (Auth::user()->isAbleTo('customer delete'))
        {
            if(!$customer){
                return redirect()->back()->with('error', __('Permission denied.'));
            }

            if(module_is_active('CustomField')){
                $customFields = \Workdo\CustomField\Entities\CustomField::where('module','Account')->where('sub_module','Customer')->get();
                foreach($customFields as $customField)
                {
                    $value = \Workdo\CustomField\Entities\CustomFieldValue::where('record_id', '=', $customer->id)->where('field_id',$customField->id)->first();
                    if(!empty($value)){
                        $value->delete();
                    }
                }
            }

            event(new DestroyCustomer($customer));
            $customer->delete();
            return redirect()->route('customer.index')->with('success', __('The customer has been deleted.'));
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
    function customerNumber()
    {
        $latest = Customer::where('workspace',getActiveWorkSpace())->latest()->first();
        if (!$latest)
        {
            return 1;
        }

        return $latest->customer_id + 1;
    }
    public function statement(Request $request, $id)
    {
        $customer = Customer::find($id);
        $invoice   = Invoice::where('created_by', '=', creatorId())->where('workspace',getActiveWorkSpace())->where('customer_id', '=', $customer->id)->get()->pluck('id');
        $invoice_payment=InvoicePayment::whereIn('invoice_id',$invoice);
        if(!empty($request->from_date)&& !empty($request->until_date))
        {
            $invoice_payment->whereBetween('date',  [$request->from_date, $request->until_date]);

            $data['from_date']  = $request->from_date;
            $data['until_date'] = $request->until_date;
        }
        else
        {
            $data['from_date']  = date('Y-m-1');
            $data['until_date'] = date('Y-m-t');
            $invoice_payment->whereBetween('date',  [$data['from_date'], $data['until_date']]);
        }

        $invoice_payment=$invoice_payment->get();
        $currencySymbol = !empty(company_setting('defult_currancy_symbol')) ? company_setting('defult_currancy_symbol') : '$' ;
        $responseData = [
            'data' => $invoice_payment,
            'currencySymbol' => $currencySymbol,
        ];

        return response()->json($responseData);
    }
    public function grid()
    {
        if(\Auth::user()->isAbleTo('customer manage'))
        {
            if(Auth::user()->type == 'company')
            {
                $customers = User::where('workspace_id',getActiveWorkSpace())
                            ->join('customers', 'users.id', '=', 'customers.user_id')
                            ->where('users.type', 'Client')
                            ->select('users.*','customers.*', 'users.name as name', 'users.email as email', 'users.id as id');
            }
            else{
                $customers =  User::where('workspace_id', getActiveWorkSpace())->where('users.id',Auth::user()->id)
                              ->join('customers', 'users.id', '=', 'customers.user_id')
                              ->where('users.type', 'Client')
                              ->select('users.*', 'customers.*', 'users.name as name', 'users.email as email', 'users.id as id', 'users.mobile_no as contact', 'customers.balance');
            }
            $customers = $customers->paginate(11);
            return view('account::customer.grid', compact('customers'));
        }
        else
        {
            
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function fileImportExport()
    {
        if(Auth::user()->isAbleTo('customer import'))
        {
            return view('account::customer.import');
        }
        else
        {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function fileImport(Request $request)
    {
        if(Auth::user()->isAbleTo('customer import'))
        {
            session_start();

            $error = '';

            $html = '';

            if ($request->file->getClientOriginalName() != '') {
                $file_array = explode(".", $request->file->getClientOriginalName());

                $extension = end($file_array);
                if ($extension == 'csv') {
                    $file_data = fopen($request->file->getRealPath(), 'r');

                    $file_header = fgetcsv($file_data);
                    $html .= '<table class="table table-bordered"><tr>';

                    for ($count = 0; $count < count($file_header); $count++) {
                        $html .= '
                                <th>
                                    <select name="set_column_data" class="form-control set_column_data" data-column_number="' . $count . '">
                                    <option value="">Set Count Data</option>
                                    <option value="name">Name</option>
                                    <option value="email">Email</option>
                                    <option value="password">Password</option>
                                    <option value="contact">Contact</option>
                                    <option value="billing_name">Billing Name</option>
                                    <option value="billing_country">Billing Country</option>
                                    <option value="billing_state">Billing State</option>
                                    <option value="billing_city">Billing City</option>
                                    <option value="billing_phone">Billing Phone</option>
                                    <option value="billing_zip">Billing Zip</option>
                                    <option value="billing_address">Billing Address</option>
                                    <option value="shipping_name">Shipping Name</option>
                                    <option value="shipping_country">Shipping Country</option>
                                    <option value="shipping_state">Shipping State</option>
                                    <option value="shipping_city">Shipping City</option>
                                    <option value="shipping_phone">Shipping Phone</option>
                                    <option value="shipping_zip">Shipping Zip</option>
                                    <option value="shipping_address">Shipping Address</option>
                                    </select>
                                </th>
                                ';

                    }
                    $html .= '</tr>';
                    $limit = 0;
                    while (($row = fgetcsv($file_data)) !== false) {
                        $limit++;

                        $html .= '<tr>';

                        for ($count = 0; $count < count($row); $count++) {
                            $html .= '<td>' . $row[$count] . '</td>';
                        }

                        $html .= '</tr>';

                        $temp_data[] = $row;

                    }
                    $_SESSION['file_data'] = $temp_data;
                } else {
                    $error = 'Only <b>.csv</b> file allowed';
                }
            } else {

                $error = 'Please Select CSV File';
            }
            $output = array(
                'error' => $error,
                'output' => $html,
            );


            return json_encode($output);
        }
        else
        {
            return redirect()->back()->with('error', 'permission Denied');
        }

    }

    public function fileImportModal()
    {
        if(Auth::user()->isAbleTo('customer import'))
        {
            return view('account::customer.import_modal');
        }
        else
        {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function customerImportdata(Request $request)
    {
        if(Auth::user()->isAbleTo('customer import'))
        {
            session_start();
            $html = '<h3 class="text-danger text-center">Below data is not inserted</h3></br>';
            $flag = 0;
            $html .= '<table class="table table-bordered"><tr>';
            $file_data = $_SESSION['file_data'];

            unset($_SESSION['file_data']);

            $user = \Auth::user();

            $roles            = Role::where('created_by',creatorId())->where('name','client')->first();
            $users_count = 0;
            foreach ($file_data as $row) {
                $canUse=  PlanCheck('User',Auth::user()->id);
                    if($canUse == false)
                    {
                        return response()->json([
                            'html' => false,
                            'response' =>'Total ' .   $users_count  . ' Number of users Imported , You have maxed out the total number of User allowed on your current plan',
                        ]);
                    }
                $customer = Customer::where('created_by',creatorId())->where('workspace',getActiveWorkSpace())->Where('email', 'like',$row[$request->email])->get();

                if($customer->isEmpty()){
                    try {
                        $user = User::create(
                            [
                                'name' =>$row[$request->name],
                                'email' => $row[$request->email],
                                'mobile_no' => $row[$request->contact],
                                'password' => Hash::make($row[$request->password]),
                                'email_verified_at' => date('Y-m-d h:i:s'),
                                'type' => !empty($roles->name)?$roles->name:'client',
                                'lang' => 'en',
                                'workspace_id' => getActiveWorkSpace(),
                                'active_workspace' =>getActiveWorkSpace(),
                                'created_by' => creatorId(),
                                ]
                            );
                            $users_count = $users_count + 1;
                            $user->addRole($roles->id);

                        Customer::create([
                            'customer_id' => $this->customerNumber(),
                            'user_id' => $user->id,
                            'name' => $row[$request->name],
                            'email' => $row[$request->email],
                            'password' => $row[$request->password],
                            'contact' => $row[$request->contact],
                            'billing_name' => $row[$request->billing_name],
                            'billing_country' => $row[$request->billing_country],
                            'billing_state' => $row[$request->billing_state],
                            'billing_city' => $row[$request->billing_city],
                            'billing_phone' => $row[$request->billing_phone],
                            'billing_zip' => $row[$request->billing_zip],
                            'billing_address' => $row[$request->billing_address],
                            'shipping_name' => $row[$request->shipping_name],
                            'shipping_country' => $row[$request->shipping_country],
                            'shipping_state' => $row[$request->shipping_state],
                            'shipping_city' => $row[$request->shipping_city],
                            'shipping_phone' => $row[$request->shipping_phone],
                            'shipping_zip' => $row[$request->shipping_zip],
                            'shipping_address' => $row[$request->shipping_address],
                            'created_by' => creatorId(),
                            'workspace' => getActiveWorkSpace(),
                        ]);



                    }
                    catch (\Exception $e)
                    {
                        $flag = 1;
                        $html .= '<tr>';

                        $html .= '<td>' . $row[$request->name] . '</td>';
                        $html .= '<td>' . $row[$request->email] . '</td>';
                        $html .= '<td>' . $row[$request->password] . '</td>';
                        $html .= '<td>' . $row[$request->contact] . '</td>';
                        $html .= '<td>' . $row[$request->billing_name] . '</td>';
                        $html .= '<td>' . $row[$request->billing_country] . '</td>';
                        $html .= '<td>' . $row[$request->billing_state] . '</td>';
                        $html .= '<td>' . $row[$request->billing_city] . '</td>';
                        $html .= '<td>' . $row[$request->billing_phone] . '</td>';
                        $html .= '<td>' . $row[$request->billing_zip] . '</td>';
                        $html .= '<td>' . $row[$request->billing_address] . '</td>';
                        $html .= '<td>' . $row[$request->shipping_name] . '</td>';
                        $html .= '<td>' . $row[$request->shipping_country] . '</td>';
                        $html .= '<td>' . $row[$request->shipping_state] . '</td>';
                        $html .= '<td>' . $row[$request->shipping_city] . '</td>';
                        $html .= '<td>' . $row[$request->shipping_phone] . '</td>';
                        $html .= '<td>' . $row[$request->shipping_zip] . '</td>';
                        $html .= '<td>' . $row[$request->shipping_address] . '</td>';

                        $html .= '</tr>';
                    }
                }
                else
                {
                    $flag = 1;
                    $html .= '<tr>';

                    $html .= '<td>' . $row[$request->name] . '</td>';
                    $html .= '<td>' . $row[$request->email] . '</td>';
                    $html .= '<td>' . $row[$request->password] . '</td>';
                    $html .= '<td>' . $row[$request->contact] . '</td>';
                    $html .= '<td>' . $row[$request->billing_name] . '</td>';
                    $html .= '<td>' . $row[$request->billing_country] . '</td>';
                    $html .= '<td>' . $row[$request->billing_state] . '</td>';
                    $html .= '<td>' . $row[$request->billing_city] . '</td>';
                    $html .= '<td>' . $row[$request->billing_phone] . '</td>';
                    $html .= '<td>' . $row[$request->billing_zip] . '</td>';
                    $html .= '<td>' . $row[$request->billing_address] . '</td>';
                    $html .= '<td>' . $row[$request->shipping_name] . '</td>';
                    $html .= '<td>' . $row[$request->shipping_country] . '</td>';
                    $html .= '<td>' . $row[$request->shipping_state] . '</td>';
                    $html .= '<td>' . $row[$request->shipping_city] . '</td>';
                    $html .= '<td>' . $row[$request->shipping_phone] . '</td>';
                    $html .= '<td>' . $row[$request->shipping_zip] . '</td>';
                    $html .= '<td>' . $row[$request->shipping_address] . '</td>';

                    $html .= '</tr>';
                }
            }

            $html .= '
                            </table>
                            <br />
                            ';
            if ($flag == 1)
            {

                return response()->json([
                            'html' => true,
                    'response' => $html,
                ]);
            } else {
                return response()->json([
                    'html' => false,
                    'response' => 'Data Imported Successfully',
                ]);
            }
        }
        else
        {
            return redirect()->back()->with('error', 'permission Denied');
        }
    }
}
