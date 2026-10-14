@extends('layouts.app')

@section('title', 'User management')

@section('content')
    <header class="page-heading">
        <div class="page-heading-copy">
            <span class="page-eyebrow">CARE COMMUNITY</span>
            <h1>User management</h1>
            <p>Manage accounts and the people in your care network.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.dashboard') }}">Create a user</a>
    </header>
    <section class="card">
        <div class="surface-header"><div><h2>Accounts</h2><p>Member directory.</p></div></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
                <tbody>
                @forelse ($users as $account)
                    <tr>
                        <td>{{ $account->name }}</td>
                        <td>{{ $account->email }}</td>
                        <td><span class="badge text-bg-light">{{ $account->role->value }}</span></td>
                        <td>{{ $account->created_at->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-secondary text-center py-4">No users found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $users->links() }}</div>
    </section>
@endsection
