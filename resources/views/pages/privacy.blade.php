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

        <h1 style="font-size: 28px; font-weight: 700; color: #0f172a; margin: 0 0 6px 0;">Privacy Policy</h1>
        <p style="font-size: 14px; color: #64748b; margin-bottom: 30px;">Effective Date: June 2026</p>

        <div style="font-size: 15px; color: #334155; line-height: 1.8;">
            <p>
                The Provincial Health Office Supply Portal (“the Portal”) is committed to protecting your privacy. This
                Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our
                website.
            </p>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">Information We Collect</h2>
            <p>
                We collect only the minimum personal information necessary to provide you with accurate medicine
                availability data and to maintain the security of the Portal:
            </p>
            <ul style="margin-left: 20px; margin-bottom: 16px;">
                <li><strong>Usage Data</strong> – Your search queries, municipality selection, and pages visited help us understand how the public uses the Portal. No personally identifiable information is stored from public searches.</li>
                <li><strong>Account Information</strong> – For authorized staff (RHU Admins, Staff), we store login credentials (username, hashed password) and activity logs tied to their account. This data is used solely for system access control, audit trails, and internal accountability.</li>
            </ul>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">How We Use Your Information</h2>
            <p>
                We use the collected information to:
            </p>
            <ul style="margin-left: 20px; margin-bottom: 16px;">
                <li>Display real‑time medicine and vaccine availability across Rural Health Units.</li>
                <li>Authenticate authorized users and enforce role‑based access controls.</li>
                <li>Maintain an audit log of staff actions for internal monitoring and security.</li>
                <li>Improve the Portal’s performance and user experience.</li>
            </ul>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">Data Sharing</h2>
            <p>
                We <strong>do not</strong> sell, trade, or share your personal information with third parties except as
                required by Philippine law (e.g., Data Privacy Act of 2012). Activity logs and user credentials are stored
                securely and accessible only to the Provincial Health Office administrator.
            </p>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">Data Retention</h2>
            <p>
                Public search data is not permanently stored. Authorized user activity logs are retained for <strong>30 days</strong>
                and then automatically deleted to maintain system performance and minimize privacy risks.
            </p>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">Security</h2>
            <p>
                We implement appropriate technical and organizational measures to protect your information against
                unauthorized access, alteration, disclosure, or destruction. All data transmissions are secured using
                standard encryption protocols.
            </p>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">Your Rights</h2>
            <p>
                Under the Data Privacy Act of 2012, you have the right to access, correct, or request deletion of your
                personal data. For any concerns, please contact the Provincial Health Office through the details provided
                below.
            </p>

            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 30px;">Contact Us</h2>
            <p>
                If you have questions about this Privacy Policy, you may reach us at:<br>
                <strong>Email:</strong> iphoantique@gmail.com<br> 
                <strong>Phone:</strong> +63 36 540 7054
            </p>
        </div>
    </div>

</div>
@endsection