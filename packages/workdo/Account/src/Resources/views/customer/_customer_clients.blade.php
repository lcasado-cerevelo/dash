<div class="card mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>{{ __('Customer\'s Clients') }}</strong>
    <button type="button" class="btn btn-sm btn-primary" id="add-customer-client">
      {{ __('Add Row') }}
    </button>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0 align-middle" id="customer-clients-table">
        <thead>
          <tr>
            <th style="min-width: 180px">{{ __('Name') }} *</th>
            <th style="min-width: 120px">{{ __('Number') }}</th>
            <th style="min-width: 160px">{{ __('Contact') }}</th>
            <th style="min-width: 200px">{{ __('Email') }}</th>
            <th style="min-width: 160px">{{ __('Phone') }}</th>
            <th style="min-width: 220px">{{ __('Address') }}</th>
            <th style="min-width: 160px">{{ __('City') }}</th>
            <th style="min-width: 160px">{{ __('State') }}</th>
            <th style="min-width: 140px">{{ __('Zip') }}</th>
            <th style="min-width: 160px">{{ __('Country') }}</th>
            <th style="min-width: 220px">{{ __('Notes') }}</th>
            <th style="min-width: 240px">{{ __('Assigned Users') }}</th>
            <th style="width: 48px"></th>
          </tr>
        </thead>
        <tbody>
          @php
            $rows = old('customer_clients');
            if ($rows === null) {
                if (isset($customer)) {
                    $rows = $customer->customerClients?->toArray() ?? [];
                } elseif (isset($company)) {
                    $rows = $company->customerClients?->toArray() ?? [];
                } else {
                    $rows = [];
                }
            }
          @endphp

          @forelse($rows as $i => $row)
            <tr>
              <td class="d-none">
                <input type="hidden" name="customer_clients[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
              </td>

              <td>
                <input class="form-control" name="customer_clients[{{ $i }}][name]"
                       value="{{ $row['name'] ?? '' }}" maxlength="255" required placeholder="{{ __('Enter Name') }}">
              </td>

              <td>
                <input class="form-control" name="customer_clients[{{ $i }}][external_code]"
                       value="{{ $row['external_code'] ?? '' }}" maxlength="50" placeholder="{{ __('Enter Number') }}">
              </td>

              <td>
                <input class="form-control" name="customer_clients[{{ $i }}][contact]"
                       value="{{ $row['contact'] ?? '' }}" maxlength="255" placeholder="{{ __('Enter Contact') }}">
              </td>

              <td>
                <input type="email" class="form-control" name="customer_clients[{{ $i }}][email]"
                       value="{{ $row['email'] ?? '' }}" maxlength="255" placeholder="{{ __('Enter Email') }}">
              </td>

              <td>
                <input class="form-control" name="customer_clients[{{ $i }}][phone]"
                       value="{{ $row['phone'] ?? '' }}" maxlength="255" placeholder="{{ __('Enter Phone') }}">
              </td>

              <td>
                <textarea class="form-control" name="customer_clients[{{ $i }}][address]" rows="2"
                          placeholder="{{ __('Enter Address') }}">{{ $row['address'] ?? '' }}</textarea>
              </td>

              <td>
                <input class="form-control" name="customer_clients[{{ $i }}][city]"
                       value="{{ $row['city'] ?? '' }}" maxlength="255"  placeholder="{{ __('Enter City') }}">
              </td>

              <td>
                <input class="form-control" name="customer_clients[{{ $i }}][state]"
                       value="{{ $row['state'] ?? '' }}" maxlength="255"  placeholder="{{ __('Enter State') }}">
              </td>

              <td>
                <input class="form-control" name="customer_clients[{{ $i }}][zip]"
                       value="{{ $row['zip'] ?? '' }}" maxlength="255"  placeholder="{{ __('Enter Zip Code') }}">
              </td>

              <td>
                <input class="form-control" name="customer_clients[{{ $i }}][country]"
                       value="{{ $row['country'] ?? '' }}" maxlength="255"  placeholder="{{ __('Enter Country') }}">
              </td>

              <td>
                <textarea class="form-control" name="customer_clients[{{ $i }}][notes]" rows="2"
                          placeholder="{{ __('Notes') }}">{{ $row['notes'] ?? '' }}</textarea>
              </td>
              <td>
                  @php
                    $assignedUserId = $row['assigned_user_id'] ?? ($row['assigned_user_ids'][0] ?? '');
                  @endphp
                    <select class="form-control customer-client-assigned-users" name="customer_clients[{{ $i }}][assigned_user_id]">
                    @foreach(($assignableUsers ?? collect()) as $u)
                        <option value="{{ $u->id }}" @selected((string)$u->id === (string)$assignedUserId)>{{ $u->name }}</option>
                    @endforeach
                  </select>
              </td>
              <td class="text-nowrap">
                <button type="button" class="btn btn-sm btn-danger remove-row" title="{{ __('Remove') }}">&times;</button>
              </td>
            </tr>
          @empty
            {{-- sin filas iniciales --}}
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Plantilla oculta para nuevas filas --}}
<template id="customer-client-row-template">
<tr>
  <td class="d-none">
    <input type="hidden" name="customer_clients[__INDEX__][id]">
  </td>

  <td>
    <input class="form-control" name="customer_clients[__INDEX__][name]" maxlength="255" required placeholder="{{ __('Enter Name') }}">
  </td>
  
  <td>
    <input class="form-control" name="customer_clients[__INDEX__][external_code]" maxlength="50" placeholder="{{ __('Enter Number') }}">
  </td>

  <td>
    <input class="form-control" name="customer_clients[__INDEX__][contact]" maxlength="255" placeholder="{{ __('Enter Contact') }}">
  </td>

  <td>
    <input type="email" class="form-control" name="customer_clients[__INDEX__][email]" maxlength="255" placeholder="{{ __('Enter Email') }}">
  </td>

  <td>
    <input class="form-control" name="customer_clients[__INDEX__][phone]" maxlength="255" placeholder="{{ __('Enter Phone') }}">
  </td>

  <td>
    <textarea class="form-control" name="customer_clients[__INDEX__][address]" rows="2" placeholder="{{ __('Enter Address') }}"></textarea>
  </td>
  
  <td>
    <input class="form-control" name="customer_clients[__INDEX__][city]" maxlength="255" placeholder="{{ __('Enter City') }}">
  </td>

  <td>
    <input class="form-control" name="customer_clients[__INDEX__][state]" maxlength="255" placeholder="{{ __('Enter State') }}">
  </td>

  <td>
    <input class="form-control" name="customer_clients[__INDEX__][zip]" maxlength="255" placeholder="{{ __('Enter Zip Code') }}">
  </td>

  <td>
    <input class="form-control" name="customer_clients[__INDEX__][country]" maxlength="255" placeholder="{{ __('Enter Country') }}">
  </td>

  <td>
    <textarea class="form-control" name="customer_clients[__INDEX__][notes]" rows="2" placeholder="{{ __('Notes') }}"></textarea>
  </td>
  <td>
        <select class="form-control customer-client-assigned-users" name="customer_clients[__INDEX__][assigned_user_id]">
        @foreach(($assignableUsers ?? collect()) as $u)
          <option value="{{ $u->id }}">{{ $u->name }}</option>
        @endforeach
      </select>
  </td>
  <td class="text-nowrap">
    <button type="button" class="btn btn-sm btn-danger remove-row" title="{{ __('Remove') }}">&times;</button>
  </td>
</tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function initAssignedUsersSelect(scope) {
        if (window.jQuery && $.fn.select2) {
            $(scope).find('.customer-client-assigned-users').select2({
                width: '100%',
                placeholder: 'Select assigned user',
                allowClear: true
            });
        }
    }

    initAssignedUsersSelect(document);

    document.getElementById('add-customer-client')?.addEventListener('click', function () {
        setTimeout(function () {
            initAssignedUsersSelect(document);
        }, 50);
    });
});
</script>
