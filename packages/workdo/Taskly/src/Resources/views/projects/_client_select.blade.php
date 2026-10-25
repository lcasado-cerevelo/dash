<div class="form-group col-md-12">
    {{ Form::label('client_id', __('Client'), ['class'=>'form-label']) }}
    @php
        $clients = $clients ?? collect();
        $selected = isset($selectedClientId) ? (string)$selectedClientId : (string)old('client_id');
    @endphp
    <select id="client_id" name="client_id" class="form-control choices">
        <option value="">{{ __('— Select —') }}</option>
        @foreach($clients as $c)
            <option value="{{ $c->id }}" {{ $selected === (string)$c->id ? 'selected' : '' }}>
                {{ $c->name }} — {{ $c->email }}
            </option>
        @endforeach
    </select>    
    

    @error('client_id') <p class="text-danger">{{ $message }}</p> @enderror
    @if($clients->isEmpty())
        <div class="alert alert-info mt-2">
            {{ __('No clients found in this workspace.') }}
            <a href="{{ route('users.create') }}" target="_blank">{{ __('Create Client User') }}</a>
        </div>
    @endif
</div>