<?php
// baseado em https://github.com/razier/Littlechief
// icons https://iconscout.com/
require '3-protect.php';
date_default_timezone_set("America/Sao_Paulo");

if (empty($_GET['do'])) {
    browser();
} else if ($_GET['do'] == "editor") {
    if (empty($_GET['action'])) {
        editor();
    } else if ($_GET['action'] == "load") {
        load(isset($_GET['file']) ? $_GET['file'] : '');
    } else if ($_GET['action'] == "save") {
        save(isset($_GET['file']) ? $_GET['file'] : '');
    } else if ($_GET['action'] == "download") {
        download(isset($_GET['file']) ? $_GET['file'] : '');
    } else if ($_GET['action'] == "delete") {
        deleteFile(isset($_GET['file']) ? $_GET['file'] : '');
    } else if ($_GET['action'] == "create") {
        createNewFile();
    }
}

function validateFilePath($filename) {
    $realBase = realpath('./');
    $realUserPath = realpath($filename);
    if ($realUserPath === false || strpos($realUserPath, $realBase) !== 0) {
        die("Acesso negado.");
    }
    return $filename;
}

function deleteFile($filename) {
    if (empty($filename)) {
        die("Erro: Arquivo não especificado.");
    }
    $filename = validateFilePath($filename);
    if (!file_exists($filename)) {
        http_response_code(404);
        die("Erro: Arquivo não encontrado.");
    }
    if (!unlink($filename)) {
        http_response_code(500);
        die("Erro ao excluir o arquivo.");
    } else {
        header("Location: inicio.php");
        exit; 
    }
}

function createNewFile() {
    $filename = date("Y-m-d_H-i-s") . '.txt';
    $file = fopen($filename, 'w');
    if (!$file) {
        http_response_code(500);
        die("Erro ao criar o arquivo.");
    }
    fclose($file);
    header("Location: ?do=editor&file=" . urlencode($filename));
    exit;
}

function editor() {
    $editorfilename = basename($_SERVER['PHP_SELF']);
    $filename = isset($_GET['file']) ? $_GET['file'] : '';
    if (empty($filename)) {
        echo "Erro: Arquivo não especificado.";
        exit;
    }
    xhtml_head(true);
    ?>
    <div class="menulink"><img src="assets/img/nTexto.png" alt="nTexto"> <a href="inicio.php"><img src="assets/img/folder.png" alt="Arquivos"></a>
        <a href="?do=editor&action=create" id="but_create"><img src="assets/img/new.png" alt="Novo"></a>
        <a href="" accesskey="s" id="but_save"><img src="assets/img/save.png" alt="Salvar"></a>
        <a onClick="return confirm('TEM CERTEZA QUE QUER DESFAZER?')" href="" accesskey="r" id="but_revert"><img src="assets/img/undo.png" alt="Desfazer"></a>
        <a href="?do=editor&action=download&file=<?php echo $_GET['file']; ?>" accesskey="d"><img src="assets/img/download.png" alt="Baixar"></a>
        <a href="#" id="but_print"><img src="assets/img/print.png" alt="Imprimir"></a>
        <a onClick="return confirm('APAGAR TEXTO?')" href="?do=editor&action=delete&file=<?php echo urlencode($filename); ?>" id="but_delete"><img src="assets/img/delete.png" alt="Excluir"></a>
        <a href="logout.php"><img src="assets/img/exit.png" alt="Sair"></a>
</div>
    <div class="nomedoarquivo" id="nomedoarquivo"><span><?php echo htmlspecialchars($filename); ?></span></div>
    
    <textarea id="code" name="code" class="editor" autofocus></textarea>   
    <div id="message"></div>
    
    <script>
        var editor;
        var docdata = "";

        document.addEventListener("DOMContentLoaded", function() {
            bind();
        });

        function bind() {
            editor = CodeMirror.fromTextArea(document.getElementById("code"), {
                lineNumbers: true,
                matchBrackets: true,
                mode: "application/x-httpd-php",
                indentUnit: 4,
                indentWithTabs: true,
                lineWrapping: true,
            });

            loadDoc('<?php echo htmlspecialchars($filename, ENT_QUOTES); ?>');

            document.getElementById("but_save").addEventListener("click", function(e) {
                e.preventDefault();
                saveDoc('<?php echo htmlspecialchars($filename, ENT_QUOTES); ?>', editor.getValue());
            });

            document.getElementById("but_revert").addEventListener("click", function(e) {
                e.preventDefault();
                loadDoc('<?php echo htmlspecialchars($filename, ENT_QUOTES); ?>');
            });

            window.onbeforeunload = function() {
                if (docdata !== editor.getValue()) {
                    return "Existem mudanças para serem salvas.";
                }
            };

            document.getElementById("but_print").addEventListener("click", function(e) {
                e.preventDefault();
                window.print();
            });
        }

        function loadDoc(filename) {
            var url = '<?php echo htmlspecialchars($editorfilename, ENT_QUOTES); ?>?do=editor&action=load&file=' + encodeURIComponent(filename);
            setMessage("Carregando...");
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        editor.setValue(xhr.responseText);
                        docdata = editor.getValue();
                        setMessage("");
                    } else {
                        setMessage("Erro ao carregar arquivo.");
                        console.error(xhr.statusText);
                    }
                }
            };
            xhr.send();
        }

        function saveDoc(filename, value) {
            var url = '<?php echo htmlspecialchars($editorfilename, ENT_QUOTES); ?>?do=editor&action=save&file=' + encodeURIComponent(filename);
            setMessage("Salvando...");
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        docdata = editor.getValue();
                        setMessage("Salvo com sucesso.");
                    } else {
                        setMessage("Erro ao salvar.");
                        console.error(xhr.statusText);
                    }
                }
            };
            xhr.send('data=' + encodeURIComponent(value));
        }

        function setMessage(msg) {
            document.getElementById("message").textContent = msg;
        }
    </script>
    
    <?php
    xhtml_foot();
}

function browser() {
    $filelist = [];

    if ($handle = opendir('./')) {
        while (($file = readdir($handle)) !== false) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            $ext = pathinfo($file, PATHINFO_EXTENSION);
            if (!is_dir($file) && is_writable($file) && strtolower($ext) === 'txt') {
                $filelist[] = $file;
            }
        }
        closedir($handle);
    }

    xhtml_head();
    ?>
    <div class="menulink"><img src="assets/img/nTexto.png" alt="nTexto"> <a href="inicio.php"><img src="assets/img/folder.png" alt="Arquivos"></a>
        <a href="?do=editor&action=create" id="but_create"><img src="assets/img/new.png" alt="Novo"></a>
        <img src="assets/img/savei.png" alt="Salvar">
        <img src="assets/img/undoi.png" alt="Desfazer">
        <img src="assets/img/downloadi.png" alt="Baixar">
        <img src="assets/img/printi.png" alt="Imprimir">
        <img src="assets/img/deletei.png" alt="Excluir">
        <a href="logout.php"><img src="assets/img/exit.png" alt="Sair"></a>
</div>

<div class="nomedoarquivo"><span>Textos Salvos</span></div>
    <ul id="filebrowser">
        <?php foreach ($filelist as $file): ?>
            <li>
                <a href="?do=editor&file=<?php echo urlencode($file); ?>">
                    <span class="file-title"><?php echo htmlspecialchars($file); ?></span>
                    <span class="file-modified"><?php echo date("d/m/Y H:i:s", filemtime("./" . $file)); ?></span>
                    <span class="file-size"><?php echo format_bytes(filesize("./" . $file)); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul><br><br>
    <p>&#x24B8; nTexto - github.com/robertostrabelli/ntexto</p>
    <?php
    xhtml_foot();
}

function format_bytes($size) {
    $units = array(' B', ' KB', ' MB', ' GB', ' TB');
    for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
    return round($size, 2) . $units[$i];
}

function load($filename) {
    $filename = validateFilePath($filename);

    if (!file_exists($filename)) {
        http_response_code(404);
        die("Erro: Arquivo não encontrado.");
    }

    $content = file_get_contents($filename);
    if ($content === false) {
        http_response_code(500);
        die("Erro ao ler o arquivo.");
    }

    echo $content;
}

function save($filename) {
    $filename = validateFilePath($filename);

    if (!is_writable($filename)) {
        http_response_code(403);
        die("Erro: Arquivo não é gravável.");
    }

    file_put_contents($filename, stripslashes($_POST['data']));
}

function download($filename) {
    $filename = validateFilePath($filename);

    header('Content-Type: text/plain');
    header('Content-Transfer-Encoding: Binary');
    header('Content-length: ' . filesize($filename));
    header('Content-disposition: attachment;filename=' . basename($filename));

    if (file_exists($filename)) {
        readfile($filename);
    }
}

function xhtml_head($control = false) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>nTexto</title>
        <meta name="apple-mobile-web-app-title" content="nTexto">
        <meta name="robots" content="noindex,nofollow,noimageindex,noarchive,nosnippet,nocache,nositelinkssearchbox,nopagereadaloud,notranslate,noodp,noydir,noyaca">
        <meta name="googlebot" content="none,noindex,nofollow,noimageindex,noarchive,nosnippet,nocache,nositelinkssearchbox,nopagereadaloud,notranslate,noodp,noydir,noyaca">
        <link rel="stylesheet" href="assets/css/style.css" type="text/css">
        <script src="assets/js/codemirror-compressed.js"></script>
        <script src="assets/js/zepto.min.js"></script>       
    </head>
    <body>
        <?php
        if ($control) {
            xhtml_control();
        }
        ?>
    <?php
}

function xhtml_control() {
    $editorfilename = basename($_SERVER['PHP_SELF']);
    ?>
<?php
}

function xhtml_foot() {
    ?>
    <div id="word-counter">
    <span id="char-count">C: 0</span> | 
    <span id="word-count">P: 0</span>
</div>

<script>
    function updateWordCount() {
        let text = editor.getValue();
        let charCount = text.length;
        let wordCount = text.trim().split(/\s+/).filter(word => word.length > 0).length;
        
        document.getElementById("char-count").textContent = "C: " + charCount;
        document.getElementById("word-count").textContent = "P: " + wordCount;
    }

    document.addEventListener("DOMContentLoaded", function() {
        editor.on("change", updateWordCount);
        updateWordCount(); // Atualiza na inicialização
    });
</script>

    </body>
    </html>
    <?php
}
?>