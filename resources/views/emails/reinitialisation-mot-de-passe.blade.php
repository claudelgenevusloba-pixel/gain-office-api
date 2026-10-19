<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réinitialisation du mot de passe - GAIN OFFICE</title>
</head>
<body style="margin:0;padding:0;background:#f3f5f9;font-family:Arial,sans-serif;color:#1f2937;">
    <div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;">
        <div style="background:#123b67;padding:28px;text-align:center;color:#ffffff;">
            <h1 style="margin:0;font-size:26px;">GAIN OFFICE</h1>
            <p style="margin:8px 0 0;">Gestion professionnelle</p>
        </div>
        <div style="padding:30px;">
            <h2 style="color:#123b67;">Bonjour {{ $nomUtilisateur }},</h2>
            <p>Une demande de réinitialisation de mot de passe a été effectuée pour votre compte GAIN OFFICE.</p>
            <p>Pour choisir un nouveau mot de passe, cliquez sur le bouton ci-dessous :</p>
            <div style="text-align:center;margin:30px 0;">
                <a href="{{ $lienReset }}" style="display:inline-block;background:#1677d2;color:#ffffff;text-decoration:none;padding:15px 26px;border-radius:8px;font-weight:bold;">Réinitialiser mon mot de passe</a>
            </div>
            <p style="font-size:14px;color:#4b5563;">Ce lien est valable pendant <strong>60 minutes</strong> et ne peut être utilisé qu'une seule fois.</p>
            <p style="font-size:14px;color:#4b5563;">Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail : votre mot de passe restera inchangé.</p>
        </div>
        <div style="background:#f8fafc;padding:18px;text-align:center;font-size:12px;color:#64748b;">
            Message automatique de GAIN OFFICE. Merci de ne pas répondre directement à cet e-mail.
        </div>
    </div>
</body>
</html>
