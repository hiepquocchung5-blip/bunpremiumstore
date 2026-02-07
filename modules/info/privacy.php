<?php
// modules/info/privacy.php
?>
<div class="max-w-4xl mx-auto glass p-8 rounded-xl border border-gray-700">
    <h1 class="text-3xl font-bold mb-2 text-white">Privacy Policy</h1>
    <p class="text-gray-400 mb-8 text-sm">Last updated: <?php echo date('F Y'); ?></p>
    
    <div class="space-y-8 text-gray-300 leading-relaxed">
        
        <section>
            <h2 class="text-xl font-bold text-blue-400 mb-3 flex items-center gap-2">
                <i class="fas fa-user-shield"></i> 1. Information We Collect
            </h2>
            <p>To provide our digital services, we collect the following limited information:</p>
            <ul class="list-disc ml-6 mt-2 space-y-2 text-sm text-gray-400">
                <li><strong>Account Data:</strong> Username, Email address, and encrypted Password.</li>
                <li><strong>Transaction Data:</strong> Transaction IDs (Last 6 digits) and payment screenshots for verification purposes.</li>
                <li><strong>Communication:</strong> Chat history within the order support system.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-xl font-bold text-blue-400 mb-3 flex items-center gap-2">
                <i class="fas fa-lock"></i> 2. How We Use Your Data
            </h2>
            <p>We use your data solely for:</p>
            <ul class="list-disc ml-6 mt-2 space-y-2 text-sm text-gray-400">
                <li>Processing and delivering your digital orders (Game Keys, Premium Accounts).</li>
                <li>Verifying payments via KBZPay or Wave Money.</li>
                <li>Providing customer support via our integrated chat system.</li>
            </ul>
            <p class="mt-3 text-yellow-500 text-sm"><i class="fas fa-exclamation-triangle mr-1"></i> We NEVER share your personal data with third parties or advertisers.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-blue-400 mb-3 flex items-center gap-2">
                <i class="fas fa-database"></i> 3. Data Storage & Security
            </h2>
            <p>Your data is stored securely on our servers. We use industry-standard encryption (BCrypt) for passwords. Payment screenshots are stored in a protected directory accessible only by administrators.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-blue-400 mb-3 flex items-center gap-2">
                <i class="fas fa-cookie-bite"></i> 4. Cookies
            </h2>
            <p>We use session cookies to keep you logged in and to remember your language/currency preferences. These are essential for the website's functionality.</p>
        </section>

        <div class="bg-gray-800 p-4 rounded-lg border border-gray-600 mt-6">
            <h4 class="font-bold text-white mb-1">Contact Us</h4>
            <p class="text-sm text-gray-400">If you have questions about this policy, please contact us via the <a href="index.php?module=info&page=support" class="text-blue-400 hover:underline">Support Page</a>.</p>
        </div>
    </div>
</div>