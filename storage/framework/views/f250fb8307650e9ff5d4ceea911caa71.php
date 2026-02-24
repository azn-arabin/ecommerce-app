

<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
    <h1 class="card-header">Welcome to E-Commerce Dashboard, <?php echo e($user->name); ?>! 🎉</h1>
    
    <div class="dashboard-grid">
        <div class="info-card">
            <h3>👤 Your Profile</h3>
            <p><strong>Name:</strong> <?php echo e($user->name); ?></p>
            <p><strong>Email:</strong> <?php echo e($user->email); ?></p>
            <p><strong>Member Since:</strong> <?php echo e($user->created_at->format('M d, Y')); ?></p>
        </div>

        <div class="info-card">
            <h3>🔐 Single Sign-On Status</h3>
            <p>✅ You are logged in to <strong>E-Commerce System</strong></p>
            <p>✅ You are automatically logged in to <strong>Foodpanda System</strong></p>
            <p style="margin-top: 1rem; font-size: 0.9rem;">
                With our SSO system, you can seamlessly access both platforms without logging in twice!
            </p>
            <?php if($ssoLink): ?>
                <a href="<?php echo e($ssoLink); ?>" 
                   class="sso-link">
                    🍔 Access Foodpanda Dashboard →
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div style="margin-top: 2rem; padding: 1.5rem; background-color: #f3f4f6; border-radius: 10px;">
        <h3 style="color: #333; margin-bottom: 1rem;">How SSO Works:</h3>
        <ol style="color: #555; line-height: 1.8;">
            <li>When you log in to E-Commerce, a secure SSO token is generated</li>
            <li>This token is automatically shared with the Foodpanda system</li>
            <li>You can access Foodpanda without entering credentials again</li>
            <li>When you logout from one system, you're logged out from both</li>
        </ol>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Users\Arabin\Desktop\Projects\My Project\task\task1-multi-login-sso\ecommerce-app\resources\views/dashboard.blade.php ENDPATH**/ ?>