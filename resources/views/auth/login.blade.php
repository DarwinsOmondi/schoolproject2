@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
    <section class="auth-card">
        <div class="auth-card-body">
            <a class="auth-brand" href="{{ route('login') }}">
                <span class="brand-symbol">+</span>
                <span>careflow</span>
            </a>
            <div class="page-eyebrow mb-2">WELCOME BACK</div>
            <h1>Welcome back.</h1>
            <p class="auth-subtitle">Sign in to continue your care journey. Your health space is right where you left it.</p>
            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" placeholder="you@example.com" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" id="password" type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required>
                </div>
                <div class="form-check mb-4 mt-3">
                    <input class="form-check-input" id="remember" type="checkbox" name="remember" value="1">
                    <label class="form-check-label" for="remember">Keep me signed in</label>
                </div>
                <button class="btn btn-primary w-100" type="submit">Sign in to your account <span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-footnote">Demo accounts are available for local testing after running the database seeder.</p>
        </div>
    </section>
@endsection
