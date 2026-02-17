<?php
require_once 'auth_check.php';

if (!function_exists('s')) {
    function s($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$message = "";

if (!$id) { header("Location: manage_activities.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $questions_posted = [];
    if (isset($_POST['qs'])) {
        foreach ($_POST['qs'] as $i => $q_text) {
            $questions_posted[] = [
                'question' => $q_text,
                'options'  => $_POST['opts'][$i], 
                'answer'   => $_POST['ans'][$i]  
            ];
        }
    }
    $quiz_json = json_encode($questions_posted, JSON_UNESCAPED_UNICODE);
    
    $title    = $_POST['title'] ?? '';
    $theme    = $_POST['theme'] ?? '';
    $matiere  = $_POST['matiere'] ?? '';
    $content  = $_POST['content_html'] ?? '';
    $distance = !empty($_POST['distance']) ? (int)$_POST['distance'] : 0;

    $stmt = $pdo->prepare("UPDATE activities SET title=?, theme=?, matiere=?, content_html=?, distance=?, quiz_json=? WHERE id=?");
    $stmt->execute([$title, $theme, $matiere, $content, $distance, $quiz_json, $id]);
    $message = "✅ Enregistré !";
}

$stmt = $pdo->prepare("SELECT * FROM activities WHERE id = ?");
$stmt->execute([$id]);
$activity = $stmt->fetch();
$quiz_data = json_decode($activity['quiz_json'] ?? '[]', true);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Éditeur d'activité</title>
    
    <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.js"></script>

    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        html, body { height: auto !important; overflow: visible !important; margin: 0; padding: 0; background: #f0f4f8; font-family: 'Segoe UI', sans-serif; }
        body.layout-grid { display: block !important; padding-top: 100px; }

        .main-edit-container { width: 85%; max-width: 1400px; margin: 0 auto 50px auto; padding-bottom: 120px !important; }
        .form-section { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; text-align: left; }
        .section-title { font-family: 'Fredoka', sans-serif; color: #2c3e50; border-bottom: 3px solid #f39c12; display: inline-block; margin-bottom: 20px; }
        
        label { display: block; margin: 15px 0 5px; font-weight: bold; }
        input, select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }

        /* Fix Toolbar Summernote */
        .note-editor .note-toolbar .note-btn-group { display: inline-flex !important; flex-wrap: nowrap !important; margin-right: 5px !important; }
        .note-editor .note-toolbar .note-btn { display: inline-block !important; width: auto !important; padding: 5px 10px !important; }
        .note-editor .note-toolbar i { display: inline !important; width: auto !important; }

        /* Liseré vert réponse correcte */
        .opt-input.is-correct { border: 3px solid #10b981 !important; background-color: #f0fdf4 !important; }

        .q-card { background: #f9f9f9; padding: 20px; border-radius: 15px; border: 1px solid #eee; margin-bottom: 30px; }

        .save-actions-bar { position: fixed; bottom: 0; left: 0; right: 0; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); padding: 20px 0; box-shadow: 0 -10px 25px rgba(0,0,0,0.1); z-index: 2000; display: flex; justify-content: center; }
        .btn-save-floating { width: 85vw !important; max-width: 1400px; background: var(--primary) !important; color: white !important; font-size: 1.2rem; font-weight: bold; padding: 15px; border-radius: 15px; border: none; cursor: pointer; box-shadow: 0 5px 0 #065f46; }
    </style>
</head>
<body class="layout-grid">

    <div class="status-bar" style="position: fixed; top:0; left:0; width:100%; background:white; padding:15px; z-index:9999; border-bottom:2px solid #ddd; box-sizing:border-box;">
        <div style="max-width:1400px; margin:0 auto; display:flex; justify-content:space-between; align-items:center;">
            <a href="manage_activities.php" style="text-decoration:none;">⬅️ Retour</a>
            <h2 style="margin:0;">Édition : <?= s($activity['title']) ?></h2>
            <span style="color:green; font-weight:bold;"><?= $message ?></span>
        </div>
    </div>

    <form method="POST" class="main-edit-container">
        <section class="form-section">
            <h3 class="section-title">📍 Configuration</h3>
            <label>Titre</label>
            <input type="text" name="title" value="<?= s($activity['title']) ?>">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div><label>Matière</label><input type="text" name="matiere" value="<?= s($activity['matiere']) ?>"></div>
                <div><label>Thème</label><input type="text" name="theme" value="<?= s($activity['theme']) ?>"></div>
            </div>
        </section>

        <section class="form-section">
            <h3 class="section-title">📖 Contenu</h3>
            <textarea id="summernote" name="content_html"><?= s($activity['content_html']) ?></textarea>
            <label>Distance de navigation (Difficulté)</label>
            <input type="number" name="distance" value="<?= s($activity['distance']) ?>" min="0">
        </section>

        <section class="form-section">
            <h3 class="section-title">❓ Quiz</h3>
            <?php foreach ($quiz_data as $i => $q): 
                $correct = $q['answer'] ?? $q['correct'] ?? $q['reponse'] ?? '';
            ?>
                <div class="q-card">
                    <label>Question <?= $i + 1 ?></label>
                    <input type="text" name="qs[<?= $i ?>]" value="<?= s($q['question'] ?? '') ?>" style="margin-bottom:15px;">
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <?php foreach (($q['options'] ?? []) as $j => $opt): ?>
                            <input type="text" name="opts[<?= $i ?>][<?= $j ?>]" class="opt-input" data-qindex="<?= $i ?>" data-oindex="<?= $j ?>" value="<?= s($opt) ?>">
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top:15px; background:#e8f8f5; padding:15px; border-radius:10px;">
                        <label>Réponse correcte :</label>
                        <select name="ans[<?= $i ?>]" class="ans-select" data-qindex="<?= $i ?>">
                            <?php foreach (($q['options'] ?? []) as $j => $opt): ?>
                                <option value="<?= s($opt) ?>" <?= ($opt === $correct) ? 'selected' : '' ?>><?= s($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="save-actions-bar">
            <div style="width: 85vw; max-width: 1400px; display: flex; gap: 20px;">
                <a href="preview_activity.php?id=<?= $id ?>#quiz" target="_blank" class="btn-preview-floating">
                    👁️ VOIR LE RENDU ÉLÈVE
                </a>
                
                <button type="submit" class="btn-save-floating">
                    💾 ENREGISTRER TOUTES LES MODIFICATIONS
                </button>
            </div>
        </div>
    </form>

    <script>
        $(document).ready(function() {
            $('#summernote').summernote({
                height: 400,
                tabsize: 2,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });

            function updateHighlight(qIdx) {
                const select = $(`select[name="ans[${qIdx}]"]`);
                const selectedIndex = select.prop('selectedIndex');
                $(`.opt-input[data-qindex="${qIdx}"]`).removeClass('is-correct');
                $(`.opt-input[data-qindex="${qIdx}"][data-oindex="${selectedIndex}"]`).addClass('is-correct');
            }

            $('.ans-select').each(function() { updateHighlight($(this).data('qindex')); });
            
            $('.ans-select').on('change', function() { updateHighlight($(this).data('qindex')); });

            $('.opt-input').on('input', function() {
                const qIdx = $(this).data('qindex'), oIdx = $(this).data('oindex');
                const val = $(this).val();
                const select = $(`select[name="ans[${qIdx}]"]`);
                select.find('option').eq(oIdx).text(val).val(val);
            });
        });
    </script>
</body>
</html>