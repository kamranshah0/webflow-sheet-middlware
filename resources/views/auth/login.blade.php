<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webflow Middleware - Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .glass-panel {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 min-h-screen flex items-center justify-center p-4 antialiased text-white">

    <div class="glass-panel rounded-3xl p-8 w-full max-w-md shadow-2xl relative overflow-hidden">
        <!-- Decorative blobs -->
        <div class="absolute top-0 left-0 w-32 h-32 bg-blue-500 rounded-full mix-blend-multiply filter blur-2xl opacity-20 -translate-x-10 -translate-y-10"></div>
        <div class="absolute bottom-0 right-0 w-32 h-32 bg-indigo-500 rounded-full mix-blend-multiply filter blur-2xl opacity-20 translate-x-10 translate-y-10"></div>

        <div class="relative z-10 text-center mb-8">
            <h1 class="text-3xl font-bold tracking-tight mb-2">Welcome Back</h1>
            <p class="text-blue-200 text-sm">Sign in to manage Webflow sync jobs</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="relative z-10 space-y-6">
            @csrf
            
            @if ($errors->any())
                <div class="bg-red-500/20 border border-red-500/50 text-red-200 text-sm p-3 rounded-lg">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label for="email" class="block text-sm font-medium text-blue-200 mb-1">Email Address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full bg-slate-900/50 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-blue-300/50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                    placeholder="admin@example.com">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-blue-200 mb-1">Password</label>
                <input type="password" id="password" name="password" required
                    class="w-full bg-slate-900/50 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-blue-300/50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                    placeholder="••••••••">
            </div>

            <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-500 text-white font-medium py-3 px-4 rounded-xl shadow-lg shadow-blue-500/30 transition-all focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                Sign In
            </button>
        </form>
    </div>

</body>
</html>
