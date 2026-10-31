@extends('layouts.public')

@section('content')
<div class="stack" style="max-width: 900px; margin: 0 auto;">

    <div style="
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(8px);
        border-radius: 20px;
        padding: 30px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        box-sizing: border-box;
    ">
       <a href="{{ session()->has('user_id') ? route('dashboard') : route('public.home') }}" style="
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 16px;
    font-weight: 500;
    color: #42ad8a;
    text-decoration: none;
    margin-bottom: 20px;
">
    ← Back to Home
</a>

        <h1 style="font-size: 28px; font-weight: 700; color: #0f172a; margin: 0 0 6px 0;">Accessibility Statement</h1>
        <p style="font-size: 14px; color: #64748b; margin-bottom: 30px;">Our commitment to inclusive access</p>

        <div style="font-size: 15px; color: #334155; line-height: 1.8;">
            <p>
                The Provincial Health Office Supply Portal is dedicated to making its website accessible to all users,
                including those with disabilities. We continually strive to improve the user experience for everyone
                and apply relevant accessibility standards.
            </p>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">Measures We Take</h2>
            <ul style="margin-left: 20px; margin-bottom: 16px;">
                <li><strong>Readable Typography</strong> – We use clear, sans‑serif fonts with sufficient size and line spacing to improve readability.</li>
                <li><strong>Colour Contrast</strong> – Text and important interface elements meet WCAG AA contrast ratios to ensure visibility for users with low vision.</li>
                <li><strong>Screen Reader Support</strong> – Semantic HTML (headings, lists, form labels) is used throughout so assistive technologies can interpret page content correctly.</li>
                <li><strong>Responsive Design</strong> – The Portal automatically adjusts to different screen sizes and orientations, including mobile phones and tablets.</li>
            </ul>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">Ongoing Improvements</h2>
            <p>
                We recognize that accessibility is an ongoing effort. We regularly review the Portal and aim to fix
                any issues promptly. If you encounter any accessibility barriers, please let us know.
            </p>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">Feedback &amp; Contact</h2>
            <p>
                We welcome your feedback on the accessibility of this Portal. Please contact us at:<br>
                <strong>Email:</strong> iphoantique@gmail.com<br>
                <strong>Phone:</strong> +63 36 540 7054<br><br>
                We aim to respond to accessibility‑related inquiries within 3–5 working days.
            </p>
        </div>
    </div>

</div>
@endsection