@php
    $mailboxes = \Workdo\Taskly\Entities\TicketInboundMailbox::orderBy('name')->get();
@endphp

<div class="card mt-4">
    <div class="card-header">
        <h5>{{ __('Inbound Mailboxes') }}</h5>
        <small class="text-muted">{{ __('Manage inbound email mailboxes used for ticket creation.') }}</small>
    </div>
    <div class="card-body">

        <form method="POST" action="{{ route('taskly.inbound_mailboxes.store') }}" class="mb-4">
            @csrf
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">{{ __('Name') }}</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('IMAP Host') }}</label>
                    <input type="text" name="imap_host" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Port') }}</label>
                    <input type="number" name="imap_port" class="form-control" value="993" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Username') }}</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Password') }}</label>
                    <input type="password" name="password_plain" class="form-control" required>
                </div>

                <div class="col-md-2 mt-3">
                    <label class="form-label">{{ __('Folder') }}</label>
                    <input type="text" name="folder" class="form-control" value="INBOX" required>
                </div>
                <div class="col-md-2 mt-3">
                    <label class="form-label">{{ __('Processed Folder') }}</label>
                    <input type="text" name="processed_folder" class="form-control">
                </div>
                <div class="col-md-2 mt-4 pt-2">
                    <div class="form-check">
                        <input type="checkbox" name="imap_ssl" value="1" class="form-check-input" checked>
                        <label class="form-check-label">{{ __('Use SSL') }}</label>
                    </div>
                </div>
                <div class="col-md-2 mt-4 pt-2">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" checked>
                        <label class="form-check-label">{{ __('Active') }}</label>
                    </div>
                </div>
                <div class="col-md-2 mt-4 pt-1">
                    <button type="submit" class="btn btn-primary">{{ __('Add Mailbox') }}</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Host') }}</th>
                        <th>{{ __('Port') }}</th>
                        <th>{{ __('Username') }}</th>
                        <th>{{ __('Folder') }}</th>
                        <th>{{ __('Processed Folder') }}</th>
                        <th>{{ __('SSL') }}</th>
                        <th>{{ __('Active') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mailboxes as $mailbox)
                        <tr>
                            <td>{{ $mailbox->name }}</td>
                            <td>{{ $mailbox->imap_host }}</td>
                            <td>{{ $mailbox->imap_port }}</td>
                            <td>{{ $mailbox->username }}</td>
                            <td>{{ $mailbox->folder }}</td>
                            <td>{{ $mailbox->processed_folder }}</td>
                            <td>{{ $mailbox->imap_ssl ? __('Yes') : __('No') }}</td>
                            <td>{{ $mailbox->is_active ? __('Yes') : __('No') }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="collapse" data-bs-target="#edit-mailbox-{{ $mailbox->id }}">
                                    {{ __('Edit') }}
                                </button>

                                <form method="POST" action="{{ route('taskly.inbound_mailboxes.destroy', $mailbox->id) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('{{ __('Are you sure?') }}')">
                                        {{ __('Delete') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <tr class="collapse" id="edit-mailbox-{{ $mailbox->id }}">
                            <td colspan="9">
                                <form method="POST" action="{{ route('taskly.inbound_mailboxes.update', $mailbox->id) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">{{ __('Name') }}</label>
                                            <input type="text" name="name" class="form-control" value="{{ $mailbox->name }}" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">{{ __('IMAP Host') }}</label>
                                            <input type="text" name="imap_host" class="form-control" value="{{ $mailbox->imap_host }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">{{ __('Port') }}</label>
                                            <input type="number" name="imap_port" class="form-control" value="{{ $mailbox->imap_port }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">{{ __('Username') }}</label>
                                            <input type="text" name="username" class="form-control" value="{{ $mailbox->username }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">{{ __('New Password') }}</label>
                                            <input type="password" name="password_plain" class="form-control">
                                        </div>

                                        <div class="col-md-2 mt-3">
                                            <label class="form-label">{{ __('Folder') }}</label>
                                            <input type="text" name="folder" class="form-control" value="{{ $mailbox->folder }}" required>
                                        </div>
                                        <div class="col-md-2 mt-3">
                                            <label class="form-label">{{ __('Processed Folder') }}</label>
                                            <input type="text" name="processed_folder" class="form-control" value="{{ $mailbox->processed_folder }}">
                                        </div>
                                        <div class="col-md-2 mt-4 pt-2">
                                            <div class="form-check">
                                                <input type="checkbox" name="imap_ssl" value="1" class="form-check-input" {{ $mailbox->imap_ssl ? 'checked' : '' }}>
                                                <label class="form-check-label">{{ __('Use SSL') }}</label>
                                            </div>
                                        </div>
                                        <div class="col-md-2 mt-4 pt-2">
                                            <div class="form-check">
                                                <input type="checkbox" name="is_active" value="1" class="form-check-input" {{ $mailbox->is_active ? 'checked' : '' }}>
                                                <label class="form-check-label">{{ __('Active') }}</label>
                                            </div>
                                        </div>
                                        <div class="col-md-2 mt-4 pt-1">
                                            <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
                                        </div>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center">{{ __('No mailboxes found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>