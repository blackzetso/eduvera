<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Campus Visit Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #1e293b;">
    <p>Hello {{ $parentName ?? 'there' }},</p>
    <p>Thank you for booking a campus visit at <strong>{{ $schoolName }}</strong>. Your request has been received.</p>
    <p><strong>Reference:</strong> {{ $referenceCode }}</p>
    <p><strong>Student:</strong> {{ $studentName ?? '—' }}</p>
    <p><strong>Preferred date:</strong> {{ $visitDate ?? '—' }}</p>
    <p><strong>Preferred time:</strong> {{ $visitTime ?? '—' }}</p>
    @if($phone)
        <p><strong>Phone:</strong> {{ $phone }}</p>
    @endif
    <p>Our admissions team will review your request and contact you if any changes are needed.</p>
    <p>— {{ $schoolName }} Admissions</p>
</body>
</html>
