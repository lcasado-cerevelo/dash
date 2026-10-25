<div class="card">
    <div class="card-header">
        <h5>{{ __('Inbound Mailboxes') }}</h5>
    </div>
    <div class="card-body">
        <p>{{ __('Mailbox maintenance section loaded correctly.') }}</p>

        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Host') }}</th>
                    <th>{{ __('Port') }}</th>
                    <th>{{ __('Folder') }}</th>
                    <th>{{ __('Active') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($mailboxes as $mailbox)
                    <tr>
                        <td>{{ $mailbox->name }}</td>
                        <td>{{ $mailbox->imap_host }}</td>
                        <td>{{ $mailbox->imap_port }}</td>
                        <td>{{ $mailbox->folder }}</td>
                        <td>{{ $mailbox->is_active ? 'Yes' : 'No' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">{{ __('No mailboxes found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>