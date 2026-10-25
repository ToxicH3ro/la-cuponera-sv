<?php

session_start();

require_once "../app/controllers/AuthController.php";

// Si ya inició sesión, enviar al dashboard
if (isset($_SESSION["usuario_id"])) {
    header("Location: dashboard.php");
    exit;
}

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $correo = trim($_POST["correo"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($correo) || empty($password)) {

        $mensaje = "Por favor, completa todos los campos.";

    } else {

        $login = new AuthController();

        if ($login->autenticar($correo, $password)) {

            header("Location: probar-conexion.php");
            exit;

        } else {

            $mensaje = "Correo o contraseña incorrectos.";

        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Iniciar sesión - La cuponera SV</title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

</head>

<body class="login-body">

    <div class="login-container">

        <div class="login-card">

            <div class="login-header">

                <div class="login-icon">
                    🎫
                </div>

                <h1>
                    La cuponera SV
                </h1>

                <p>
                    Inicia sesión para continuar
                </p>

            </div>


            <?php if (!empty($mensaje)): ?>

                <div class="login-error">

                    <?php echo htmlspecialchars($mensaje); ?>

                </div>

            <?php endif; ?>


            <form method="POST">

                <div class="form-group">

                    <label for="correo">
                        Correo electrónico
                    </label>

                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        placeholder="ejemplo@correo.com"
                        value="<?php echo htmlspecialchars($_POST["correo"] ?? ""); ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="password">
                        Contraseña
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Ingresa tu contraseña"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="login-button"
                >
                    Iniciar sesión
                </button>

            </form>


            <div class="login-footer">

                <p>
                    Sistema cuponera SV - 2026.
                </p>

            </div>

        </div>

    </div>

</body>

</html>