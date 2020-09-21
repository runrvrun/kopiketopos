@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center whitebg">
    <img class="login-logo" src="{{asset('images/logo.png')}}"/>
    </div>
    <div class="row justify-content-center">
        <div class="col-12 primarycolorbg loginform">
            <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="form-group row">
                <div class="col-12">
                    <input placeholder="email" id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>

                    @error('email')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
            </div>

            <div class="form-group row">
                <div class="col-12">
                    <input placeholder="password" id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">

                    @error('password')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
            </div>

            <div class="form-group row">
                <div class="col-6 offset-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>

                        <label class="form-check-label" for="remember">
                            {{ __('Remember Me') }}
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-group row mb-0">
                <div class="col-12">
                    <button type="submit" class="btn btn-block btn-primary">
                        {{ __('Login') }}
                    </button>
                </div>
            </div>
        </form>
        </div>
    </div>
</div>
@endsection
@section('pagecss')
<style>
body{
    margin-top: 0px;
    margin-bottom: 0px;
}
.loginform{
    padding-top: 40px;
    padding-bottom: 40px;
    min-height: 500px;
}
.login-logo{
    width:50%;
    margin: 50px 0;
}
</style>
@endsection