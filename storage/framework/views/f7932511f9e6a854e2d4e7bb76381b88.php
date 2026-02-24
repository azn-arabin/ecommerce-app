<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'E-Commerce App'); ?> - Multi-Login SSO</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .navbar {
            background-color: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar-brand {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
        }
        
        .navbar-nav {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .nav-link, .btn-logout {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .btn-logout {
            background-color: rgba(239, 68, 68, 0.8);
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-logout:hover {
            background-color: rgba(220, 38, 38, 0.9);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            flex: 1;
        }
        
        .card {
            background-color: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .card-header {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
            color: #333;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-muted {
            color: #6b7280;
            margin-top: 1rem;
        }
        
        .text-muted a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .text-muted a:hover {
            text-decoration: underline;
        }
        
        .error-text {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .info-card h3 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
        }
        
        .info-card p {
            margin-bottom: 0.5rem;
            opacity: 0.9;
        }
        
        .sso-link {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.75rem 1.5rem;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sso-link:hover {
            background-color: rgba(255, 255, 255, 0.3);
            transform: translateX(5px);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="/" class="navbar-brand">🛍️ E-Commerce System</a>
            <div class="navbar-nav">
                <?php if(auth()->guard()->check()): ?>
                    <span class="nav-link"><?php echo e(Auth::user()->name); ?></span>
                    <form action="<?php echo e(route('logout')); ?>" method="POST" style="margin: 0;">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn-logout">Logout</button>
                    </form>
                <?php else: ?>
                    <a href="<?php echo e(route('login')); ?>" class="nav-link">Login</a>
                    <a href="<?php echo e(route('register')); ?>" class="nav-link">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if(session('success')): ?>
            <div class="alert alert-success">
                <?php echo e(session('success')); ?>

            </div>
        <?php endif; ?>

        <?php if(session('error')): ?>
            <div class="alert alert-danger">
                <?php echo e(session('error')); ?>

            </div>
        <?php endif; ?>

        <?php echo $__env->yieldContent('content'); ?>
    </div>
</body>
</html>
<?php /**PATH C:\Users\Arabin\Desktop\Projects\My Project\task\task1-multi-login-sso\ecommerce-app\resources\views/layout.blade.php ENDPATH**/ ?>