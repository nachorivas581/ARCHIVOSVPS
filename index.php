<!DOCTYPE html>
<html>
<head>
    <title>Registro de Placeros</title>
    <link rel="stylesheet" type="text/css" href="css/styles.css">
    <style>
        /* Estilos para el popup */
        .popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 20px;
            border: 2px solid #ccc;
            z-index: 9999;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            text-align: center;
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9998;
        }
        .logo-popup {
            width: 200px; /* Ajusta el tamaño del logo según sea necesario */
            height: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <img src="logomuni.jpg" alt="Logo" class="logo">
            <h1>Registro de Placeros</h1>
        </header>

        <form id="registroForm" enctype="multipart/form-data">
            <label for="nombre">Nombre de Placero:</label>
            <input type="text" id="nombre" name="nombre" required><br>

            <label for="legajo">Legajo:</label>
            <input type="text" id="legajo" name="legajo" required><br>

            <label for="plaza">Plaza en la que está:</label>
            <input type="text" id="plaza" name="plaza" required><br>

            <label for="palas_cuadrada">Palas Cuadradas:</label>
            <input type="number" id="palas_cuadrada" name="palas_cuadrada" required><br>

            <label for="palas_corazon">Palas Corazón:</label>
            <input type="number" id="palas_corazon" name="palas_corazon" required><br>

            <label for="carretillas">Carretillas:</label>
            <input type="number" id="carretillas" name="carretillas" required><br>

            <label for="rastrillos">Rastrillos:</label>
            <input type="number" id="rastrillos" name="rastrillos" required><br>

            <label for="tijeras">Tijeras de Poda:</label>
            <input type="number" id="tijeras" name="tijeras" required><br>

            <label for="barrehojas">Barre Hojas:</label>
            <input type="number" id="barrehojas" name="barrehojas" required><br>

            <label for="estado">Estado de Juegos:</label>
            <select id="estado" name="estado" required>
                <option value="Normal">Normal</option>
                <option value="Deteriorado">Deteriorado</option>
                <option value="Excelente">Excelente</option>
                <option value="Medio">Medio</option>
            </select><br>

            <label for="fotos_herramientas">Fotos de las Herramientas:</label>
            <input type="file" id="fotos_herramientas" name="fotos_herramientas[]" multiple required><br>

            <label for="fotos_juegos">Fotos de los Juegos (Solo si tiene la plaza):</label>
            <input type="file" id="fotos_juegos" name="fotos_juegos[]" multiple><br>

            <button type="submit">Guardar</button>
        </form>

        <!-- Popup y overlay -->
        <div class="overlay" id="overlay"></div>
        <div class="popup" id="popup">
            <img src="logomuni.jpg" alt="Logo" class="logo-popup">
            <p id="popup-message"></p>
            <button id="popup-button" onclick="cerrarPopup()">Cerrar</button>
        </div>
    </div>

    <script>
        document.getElementById('registroForm').addEventListener('submit', function(event) {
            event.preventDefault(); // Detenemos el envío del formulario

            var formData = new FormData(this);

            fetch('guardar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'exists') {
                    abrirPopup(data.message, true);
                } else if (data.status === 'success') {
                    abrirPopup(data.message, false);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });

        function abrirPopup(message, isEditing) {
            document.getElementById('popup-message').textContent = message;
            if (isEditing) {
                var button = document.getElementById('popup-button');
                button.textContent = 'Enviar a editar';
                button.onclick = function() {
                    window.location.href = 'editar.php?legajo=' + document.getElementById('legajo').value;
                };
            } else {
                document.getElementById('popup-button').textContent = 'Cerrar';
                document.getElementById('popup-button').onclick = cerrarPopup;
            }
            document.getElementById('overlay').style.display = 'block';
            document.getElementById('popup').style.display = 'block';
            setTimeout(cerrarPopup, 5000); // Cierra el popup automáticamente después de 5 segundos
        }

        function cerrarPopup() {
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('popup').style.display = 'none';
        }
    </script>
</body>
</html>
