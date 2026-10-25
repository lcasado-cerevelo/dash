<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\CustomerClient;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerController extends Controller
{
    public function store(CustomerRequest $request)
    {
        return DB::transaction(function () use ($request) {

            $customer = Customer::create($request->safe()->except('customer_clients'));

            $rows = collect($request->input('customer_clients', []))
                ->map(function ($r) {
                    foreach ($r as $k => $v) if (is_string($v)) $r[$k] = trim($v);
                    return $r;
                })
                ->filter(fn ($r) => filled($r['name'] ?? null))
                ->map(fn ($r) => Arr::only($r, [
                    'external_code','name','email','contact',
                    'country','state','city','phone','zip','address','notes'
                ]))
                ->values();

            if ($rows->isNotEmpty()) {
                $customer->customerClients()->createMany($rows->all());
            }

            return redirect()->route('customers.edit', $customer)
                             ->with('success', 'Customer created.');
        });
    }

    public function update(CustomerRequest $request, Customer $customer)
    {
        return DB::transaction(function () use ($request, $customer) {

            $customer->update($request->safe()->except('customer_clients'));

            // --- Solo tocar hijos si el form envió la tabla ---
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
                }

                // Borra solo si la sección estuvo presente
                if (!empty($keptIds)) {
                    $customer->customerClients()->whereNotIn('id', $keptIds)->delete();
                } else {
                    $customer->customerClients()->delete();
                }
            }

            return back()->with('success', 'Customer updated.');
        });
    }

    /**
     * Endpoint para Select2: /customers/search?q=texto&limit=20
     * También soporta precarga por id: /customers/search?id=123
     */
    public function search(Request $request)
    {
        $q     = trim((string)$request->input('q', ''));
        $id    = $request->input('id');
        $limit = (int)($request->input('limit', 20)) ?: 20;

        $query = Customer::query();

        // Multi-tenant típico (ajusta a tu esquema si aplica)
        if (Schema::hasColumn('customers', 'created_by') && Auth::check() && method_exists(Auth::user(), 'creatorId')) {
            $query->where('created_by', Auth::user()->creatorId());
        }

        // Precarga por ID (útil cuando el form ya tiene un valor seleccionado)
        if ($id) {
            $row = (clone $query)->whereKey($id)->first(['id','name','email']);
            if (!$row) return response()->json([]);
            return response()->json([[
                'id'   => $row->id,
                'text' => $row->email ? "{$row->name} ({$row->email})" : $row->name,
            ]]);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $items = $query->orderBy('name')
                       ->limit($limit)
                       ->get(['id','name','email']);

        $results = $items->map(fn ($c) => [
            'id'   => $c->id,
            'text' => $c->email ? "{$c->name} ({$c->email})" : $c->name,
        ])->values();

        return response()->json($results);
    }
}
