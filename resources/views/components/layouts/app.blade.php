@props(['title' => 'Hiring workspace'])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Teamline</title><link rel="icon" href="{{ asset('assets/favicon.svg') }}" type="image/svg+xml"><link rel="stylesheet" href="{{ asset('teamline.css') }}">
    @livewireStyles
</head>
<body class="{{ auth()->check() ? 'workspace' : 'guest' }}">
<a href="#main" class="skip-link">Skip to content</a>
@auth
<aside class="sidebar">
    <a class="brand" href="{{ route('pipeline') }}"><span class="brand-icon">t</span> teamline<span class="brand-dot">.</span></a>
    <div class="organization"><span class="org-avatar">{{ mb_substr(auth()->user()->organization->name, 0, 1) }}</span><div><strong>{{ auth()->user()->organization->name }}</strong><small>Demo workspace</small></div><span>⌄</span></div>
    <span class="nav-label">WORKSPACE</span>
    <nav aria-label="Main navigation">
        <a class="{{ request()->routeIs('pipeline','applications.*') ? 'active' : '' }}" href="{{ route('pipeline') }}"><span>▦</span> Hiring pipeline <b>↗</b></a>
        <a class="{{ request()->routeIs('jobs.*') ? 'active' : '' }}" href="{{ route('jobs.index') }}"><span>▤</span> Open roles</a>
        <a class="{{ request()->routeIs('activity') ? 'active' : '' }}" href="{{ route('activity') }}"><span>◷</span> Activity log</a>
    </nav>
    <div class="sidebar-note"><span class="spark">✦</span><strong>Thoughtful hiring.<br>One clear next step.</strong><p>Keep every conversation and decision moving together.</p></div>
    <div class="user-card"><span class="avatar lilac">{{ mb_substr(auth()->user()->name,0,1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ ucfirst(auth()->user()->role) }}</small></div><form action="{{ route('logout') }}" method="POST">@csrf<button class="logout" title="Sign out" aria-label="Sign out">↪</button></form></div>
</aside>
@endauth
<div class="page-shell">
@auth
<header class="topbar"><div><span class="breadcrumb">Workspace</span><span class="crumb-separator">/</span><strong>{{ $title }}</strong></div><span class="demo-tag"><i></i> Independent portfolio demo</span><details class="mobile-nav"><summary aria-label="Open navigation">Menu</summary><nav><a href="{{ route('pipeline') }}">Hiring pipeline</a><a href="{{ route('jobs.index') }}">Open roles</a><a href="{{ route('activity') }}">Activity log</a><form action="{{ route('logout') }}" method="POST">@csrf<button>Sign out</button></form></nav></details></header>
@endauth
<main id="main" tabindex="-1">
    @if(session('success'))<div role="status" class="flash">✓ {{ session('success') }}</div>@endif
    @if($errors->any())<div role="alert" class="error-summary"><strong>Please review the following</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    {{ $slot }}
</main>
@auth<footer class="page-footer"><span>Teamline · Original reconstruction · Fictional people and organizations</span><span>No emails are sent. All times UTC.</span></footer>@endauth
</div>
@livewireScripts
</body>
</html>
