```html
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation GAIN OFFICE</title>
</head>
<body style="margin:0;padding:0;background:#f3f5f9;font-family:Arial,sans-serif;color:#1f2937;">
    <div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;">
        <div style="background:#123b67;padding:28px;text-align:center;color:#ffffff;">
            <h1 style="margin:0;font-size:26px;">GAIN OFFICE</h1>
            <p style="margin:8px 0 0;">Gestion professionnelle</p>
        </div>

        <div style="padding:30px;">
            <h2 style="color:#123b67;">Bienvenue {{ $nomEmploye }} !</h2>

            <p>Votre compte professionnel GAIN OFFICE a été créé par l'administration.</p>

            <p>Pour commencer à utiliser votre espace, veuillez activer votre compte en cliquant sur le bouton ci-dessous.</p>

            <div style="text-align:center;margin:30px 0;">
                <a href="{{ $lienActivation }}"
                   style="display:inline-block;background:#1677d2;color:#ffffff;text-decoration:none;padding:15px 26px;border-radius:8px;font-weight:bold;">
                    Activer mon compte
                </a>
            </div>

            <p style="font-size:14px;color:#4b5563;">
                Ce lien expire le {{ $dateExpiration }}.
            </p>

            <p style="font-size:14px;color:#4b5563;">
                Si vous n'attendiez pas cette invitation, veuillez contacter l'administration.
            </p>
        </div>

        <div style="background:#f8fafc;padding:18px;text-align:center;font-size:12px;color:#64748b;">
            Message automatique de GAIN OFFICE. Merci de ne pas répondre directement à cet e-mail.
        </div>
    </div>
</body>
</html>
```
