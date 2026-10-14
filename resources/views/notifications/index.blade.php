@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
    <header class="page-heading">
        <div class="page-heading-copy">
            <span class="page-eyebrow">YOUR UPDATES</span>
            <h1>Notifications</h1>
            <p>Appointment and message updates from your care team.</p>
        </div>
        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <button class="btn btn-outline-secondary" type="submit">Mark all as read</button>
            </form>
        @endif
    </header>

    <section class="card">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-3 {{ $notification->read_at ? '' : 'list-group-item-primary' }}">
                    <div>
                        <a class="fw-semibold text-decoration-none" href="{{ $notification->data['url'] }}">{{ $notification->data['message'] }}</a>
                        <span class="d-block small text-secondary">{{ $notification->created_at->diffForHumans() }}</span>
                    </div>
                    @if (! $notification->read_at)
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-sm btn-outline-primary" type="submit">Mark read</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="empty-state"><span class="empty-state-icon" aria-hidden="true">♧</span><strong>You’re all caught up</strong><p>Updates from your care team will appear here.</p></div>
            @endforelse
        </div>
        <div class="card-body">{{ $notifications->links() }}</div>
    </section>
@endsection
