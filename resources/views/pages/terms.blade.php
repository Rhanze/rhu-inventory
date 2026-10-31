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

        <h1 style="font-size: 28px; font-weight: 700; color: #0f172a; margin: 0 0 6px 0;">Terms of Use</h1>
        <p style="font-size: 14px; color: #64748b; margin-bottom: 30px;">Last Updated: June 2026</p>

        <div style="font-size: 15px; color: #334155; line-height: 1.8;">
            <p>
                By accessing and using the Provincial Health Office Supply Portal (“the Portal”), you agree to be bound
                by these Terms of Use. If you do not agree with any part of these terms, please discontinue use of the
                Portal immediately.
            </p>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">1. Use of the Portal</h2>
            <p>
                The Portal is provided by the Provincial Health Office of Southern Antique to assist the public in
                locating available medicines and vaccines at Rural Health Units. The information displayed is for
                reference and convenience purposes only. While we strive to keep the data accurate and up‑to‑date,
                the actual availability of medicines may change without prior notice.
            </p>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">2. No Medical Advice</h2>
            <p>
                The Portal does not provide medical advice, diagnosis, or treatment. Always consult a licensed
                healthcare professional for medical concerns. Medicine availability shown here does not guarantee
                that a specific medicine will be dispensed without a valid prescription (if required) or that stocks
                will remain available by the time you visit the RHU.
            </p>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">3. Authorized Access</h2>
            <p>
                Certain sections of the Portal are restricted to authorized personnel (RHU Admins and Staff).
                Unauthorized attempts to access these sections are prohibited and may be subject to legal action
                under the Philippine Cybercrime Prevention Act of 2012 (Republic Act No. 10175).
            </p>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">4. Intellectual Property</h2>
            <p>
                All content, logos, and design elements on the Portal are owned by the Provincial Health Office unless
                otherwise stated. You may not reproduce, distribute, or modify any part of the Portal without prior
                written consent.
            </p>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">5. Limitation of Liability</h2>
            <p>
                The Provincial Health Office shall not be held liable for any direct, indirect, or consequential
                damages arising from the use of or reliance on the information provided by this Portal. This includes
                but is not limited to any inconvenience caused by outdated stock information or temporary service
                interruptions.
            </p>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">6. Changes to Terms</h2>
            <p>
                We reserve the right to modify these Terms of Use at any time. Changes will be posted on this page
                with an updated “Last Updated” date. Continued use of the Portal after any changes constitutes
                your acceptance of the new terms.
            </p>
        </div>
    </div>

</div>
@endsection