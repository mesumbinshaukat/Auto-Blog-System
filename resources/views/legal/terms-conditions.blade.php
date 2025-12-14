@extends('layouts.app')

@section('title', 'Terms & Conditions - AutoBlogSystem')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12 prose prose-lg">
    <h1>Terms and Conditions</h1>
    <p class="text-gray-600 mb-8">Last updated: {{ now()->format('F d, Y') }}</p>

    <h2>1. Introduction</h2>
    <p>These terms and conditions outline the rules and regulations for the use of AutoBlogSystem's Website.</p>

    <h2>2. Ad-Supported Access</h2>
    <p>Access to the content on AutoBlogSystem is provided to you free of charge. You acknowledge and agree that:</p>
    <ul>
        <li>We display advertisements to support the costs of hosting, content generation, and maintenance.</li>
        <li>By using this website, you consent to the display of these advertisements.</li>
        <li>We reserve the right to monetize usage patterns via non-personal data (ads) but strictly adhere to our "No Data Sale" privacy commitment.</li>
    </ul>

    <h2>3. Intellectual Property Rights</h2>
    <p>Other than the content you own, under these Terms, AutoBlogSystem and/or its licensors own all the intellectual property rights and materials contained in this Website.</p>

    <h2>4. Restrictions</h2>
    <p>You are specifically restricted from all of the following:</p>
    <ul>
        <li>publishing any Website material in any other media;</li>
        <li>selling, sublicensing and/or otherwise commercializing any Website material;</li>
        <li>publicly performing and/or showing any Website material;</li>
        <li>using this Website in any way that is or may be damaging to this Website;</li>
    </ul>

    <h2>4. Limitation of Liability</h2>
    <p>In no event shall AutoBlogSystem, nor any of its officers, directors and employees, be held liable for anything arising out of or in any way connected with your use of this Website.</p>

    <h2>5. Variation of Terms</h2>
    <p>AutoBlogSystem is permitted to revise these Terms at any time as it sees fit, and by using this Website you are expected to review these Terms on a regular basis.</p>

    <h2>6. Governing Law & Jurisdiction</h2>
    <p>These Terms will be governed by and interpreted in accordance with the laws of the State, and you submit to the non-exclusive jurisdiction of the state and federal courts located in us for the resolution of any disputes.</p>
</div>
@endsection
