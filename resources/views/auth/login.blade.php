@extends('layout')

@section('title', 'Login')

@section('content')
<div class="card" style="max-width: 500px; margin: 3rem auto;">
    <h2 class="card-header">Login to E-Commerce</h2>
    
    <form method="POST" action="{{ route('login') }}">
        @csrf
        
        <div class="form-group">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" 
                   class="form-control @error('email') is-invalid @enderror" 
                   id="email" 
                   name="email" 
                   value="{{ old('email') }}" 
                   required 
                   autofocus>
            @error('email')
                <div class="error-text">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password" class="form-label">Password</label>
            <input type="password" 
                   class="form-control @error('password') is-invalid @enderror" 
                   id="password" 
                   name="password" 
                   required>
            @error('password')
                <div class="error-text">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <div class="form-check">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember Me</label>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Login</button>
    </form>

    <div class="text-center text-muted">
        Don't have an account? <a href="{{ route('register') }}">Register here</a>
    </div>
</div>
@endsection
