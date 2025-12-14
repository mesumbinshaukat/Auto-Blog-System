@extends('layouts.app')

@section('title', 'Privacy Policy - AutoBlogSystem')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12 prose prose-lg">
    <h1>Privacy Policy</h1>
    <p class="text-gray-600 mb-8">Last updated: {{ now()->format('F d, Y') }}</p>

    <p>At AutoBlogSystem, we respect your privacy and are committed to protecting it through our compliance with this policy.</p>

    <h2>1. Information We Collect</h2>
    <p>We may collect personal information that you voluntarily provide to us when you subscribe to our newsletter, valid comment forms, or contact us. This may include your name, email address, and IP address.</p>

    <h2>2. How We Use Your Information</h2>
    <p>We use the information we collect to:</p>
    <ul>
        <li>Provide, operate, and maintain our website</li>
        <li>Improve, personalize, and expand our website</li>
        <li>Understand and analyze how you use our website</li>
        <li>Send you emails and newsletters (if subscribed)</li>
    </ul>

    <h2>3. Data Monetization & Advertisements</h2>
    <p>We believe in keeping our content free and accessible to everyone. To support the operation and maintenance of AutoBlogSystem, we display third-party advertisements (e.g., Google AdSense).</p>
    
    <p><strong>Crucial Policies regarding your Data:</strong></p>
    <ul>
        <li><strong>No Sale of Data:</strong> We do <strong>NOT</strong> sell, trade, or rent your personal identification information (such as name or email) to Data Brokers or third parties for profit.</li>
        <li><strong>Ad Usage:</strong> We <em>do</em> utilize data such as cookies and usage patterns solely to display relevant advertisements to you. This ensures you see ads that might actually be interesting, rather than random spam.</li>
        <li><strong>Third-Party Tracking:</strong> Our advertising partners (like Google) may use cookies to collect non-personal information about your visits to this and other websites in order to provide advertisements about goods and services of interest to you.</li>
    </ul>

    <h2>4. Cookies and Tracking</h2>
    <p>We use cookies to enhance your experience. Even if you "Reject" non-essential cookies via our consent banner, we may still display advertisements to you, though they may be less personalized. Cookies help us analyze traffic and serve content efficiently.</p>

    <h2>4. Third-Party Services</h2>
    <p>We may use third-party Service Providers to monitor and analyze the use of our Service (e.g., Google Analytics). These third parties have access to your Personal Data only to perform these tasks on our behalf and are obligated not to disclose or use it for any other purpose.</p>

    <h2>5. GDPR Data Protection Rights</h2>
    <p>We would like to make sure you are fully aware of all of your data protection rights. Every user is entitled to the following:</p>
    <ul>
        <li>The right to access – You have the right to request copies of your personal data.</li>
        <li>The right to rectification – You have the right to request that we correct any information you believe is inaccurate.</li>
        <li>The right to erasure – You have the right to request that we erase your personal data, under certain conditions.</li>
    </ul>

    <h2>6. Contact Us</h2>
    <p>If you have any questions about this Privacy Policy, please contact us at: info@worldoftech.company</p>
</div>
@endsection
