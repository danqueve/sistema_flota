<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id      = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;
    $patente = strtoupper(trim($_POST['patente'] ?? ''));
    $marca   = trim($_POST['marca'] ?? '');
    $modelo  = trim($_POST['modelo'] ?? '');
    $anio    = $_POST['anio'] !== '' ? (int) $_POST['anio'] : null;
    $km      = $_POST['km_actual'] !== '' ? (int) $_POST['km_actual'] : null;

    if ($patente === '') {
        $errores[] = 'La patente es obligatoria.';
    }

    if (!$errores) {
        try {
            if ($id) {
                $stmt = $pdo->prepare('UPDATE camiones SET patente=?, marca=?, modelo=?, anio=?, km_actual=? WHERE id=?');
                $stmt->execute([$patente, $marca ?: null, $modelo ?: null, $anio, $km, $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO camiones (patente, marca, modelo, anio, km_actual) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$patente, $marca ?: null, $modelo ?: null, $anio, $km]);
            }
            header('Location: camiones.php');
            exit;
        } catch (PDOException $e) {
            $errores[] = $e->getCode() === '23000'
                ? 'Ya existe un camión con esa patente.'
                : 'No se pudo guardar el camión.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado') {
    $id     = (int) $_POST['id'];
    $activo = (int) $_POST['activo'];
    $stmt = $pdo->prepare('UPDATE camiones SET activo=? WHERE id=?');
    $stmt->execute([$activo, $id]);
    header('Location: camiones.php');
    exit;
}

$registroEditar = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM camiones WHERE id=?');
    $stmt->execute([(int) $_GET['id']]);
    $registroEditar = $stmt->fetch() ?: null;
}

$camiones = $pdo->query('SELECT * FROM camiones ORDER BY activo DESC, patente')->fetchAll();

$activo = 'camiones';
$tituloPagina = 'Camiones';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1><?= $registroEditar ? 'Editar camión' : 'Nuevo camión' ?></h1>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<form method="post">
  <input type="hidden" name="accion" value="guardar">
  <input type="hidden" name="id" value="<?= $registroEditar['id'] ?? '' ?>">

  <label for="patente">Patente</label>
  <input class="campo-input" type="text" id="patente" name="patente" maxlength="10" required
    value="<?= htmlspecialchars($registroEditar['patente'] ?? '') ?>">

  <div class="fila">
    <div>
      <label for="marca">Marca</label>
      <input class="campo-input" type="text" id="marca" name="marca" maxlength="40"
        value="<?= htmlspecialchars($registroEditar['marca'] ?? '') ?>">
    </div>
    <div>
      <label for="modelo">Modelo</label>
      <input class="campo-input" type="text" id="modelo" name="modelo" maxlength="60"
        value="<?= htmlspecialchars($registroEditar['modelo'] ?? '') ?>">
    </div>
  </div>

  <div class="fila">
    <div>
      <label for="anio">Año</label>
      <input class="campo-input" type="number" id="anio" name="anio" min="1980" max="2100"
        value="<?= htmlspecialchars($registroEditar['anio'] ?? '') ?>">
    </div>
    <div>
      <label for="km_actual">Km actual</label>
      <input class="campo-input" type="number" id="km_actual" name="km_actual" min="0"
        value="<?= htmlspecialchars($registroEditar['km_actual'] ?? '') ?>">
    </div>
  </div>

  <button type="submit" class="btn">Guardar camión</button>
  <?php if ($registroEditar): ?>
    <a href="camiones.php" class="btn sec">Cancelar edición</a>
  <?php endif; ?>
</form>

<h1 class="seccion">Camiones cargados</h1>

<?php foreach ($camiones as $camion): ?>
  <div class="item <?= $camion['activo'] ? '' : 'inactivo' ?>">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($camion['patente']) ?></span>
      <span><?= htmlspecialchars(trim($camion['marca'] . ' ' . $camion['modelo'])) ?: '—' ?></span>
    </div>
    <div class="l2">
      <span><?= $camion['anio'] ? htmlspecialchars((string) $camion['anio']) : 'Sin año' ?> · <?= $camion['km_actual'] !== null ? number_format((float) $camion['km_actual'], 0, ',', '.') . ' km' : 'Sin km' ?></span>
      <?php if (!$camion['activo']): ?><span class="chip rech">inactivo</span><?php endif; ?>
    </div>
    <div class="acciones">
      <a href="camiones.php?id=<?= $camion['id'] ?>">Editar</a>
      <form method="post">
        <input type="hidden" name="accion" value="cambiar_estado">
        <input type="hidden" name="id" value="<?= $camion['id'] ?>">
        <input type="hidden" name="activo" value="<?= $camion['activo'] ? 0 : 1 ?>">
        <button type="submit"><?= $camion['activo'] ? 'Desactivar' : 'Activar' ?></button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$camiones): ?>
  <p class="nota">Todavía no hay camiones cargados.</p>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
