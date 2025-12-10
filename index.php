<?php
// Configurações
define('SENHA_USER', 'ntexto'); // apague o arquivo ntexto.sqlite e mude a senha (ntexto).
define('DB_FILE', __DIR__ . '/ntexto.sqlite');
date_default_timezone_set('America/Sao_Paulo');
if (session_status() == PHP_SESSION_NONE) session_start();

if (!file_exists(DB_FILE)) {
    init_db();
}

function open_db(){
    $db = new SQLite3(DB_FILE);
    $db->exec('PRAGMA foreign_keys = ON;');
    return $db;
}

// Inicialização do banco de dados (executar apenas uma vez)
function init_db(){
    $dbFile = DB_FILE;
    if (file_exists($dbFile)) return;
    
    $db = open_db();
    // Create tables: users (simple single admin) and notes
    $db->exec("CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        created_at TEXT
    );");

    $db->exec("CREATE TABLE notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        title TEXT DEFAULT 'Sem título',
        content TEXT,
        updated_at TEXT,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    );");

    // create default admin user with password from config (stored plain for simplicity)
    $stmt = $db->prepare('INSERT INTO users (username,password,created_at) VALUES (:u,:p,:t)');
    $stmt->bindValue(':u','user',SQLITE3_TEXT);
    $stmt->bindValue(':p',SENHA_USER,SQLITE3_TEXT);
    $stmt->bindValue(':t',date('c'),SQLITE3_TEXT);
    $stmt->execute();
}

// Roteamento baseado na ação
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Headers para compatibilidade com iOS 6
header('Content-Type: text/html; charset=utf-8');

switch($action) {
    case 'login':
        handle_login();
        break;
    case 'logout':
        handle_logout();
        break;
    case 'list':
        handle_list();
        break;
    case 'load':
        handle_load();
        break;
    case 'save':
        handle_save();
        break;
    case 'delete':
        handle_delete();
        break;
    case 'init':
        handle_init();
        break;
    default:
        if (!isset($_SESSION['user_id'])) {
            show_login();
        } else {
            show_editor();
        }
        break;
}

function handle_init() {
    init_db();
    echo "Banco criado com sucesso. Usuário: user";
    exit;
}

function handle_login() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST'){
        $pwd = isset($_POST['password']) ? $_POST['password'] : '';
        $db = open_db();
        $stmt = $db->prepare('SELECT id FROM users WHERE username = :u AND password = :p');
        $stmt->bindValue(':u','user',SQLITE3_TEXT);
        $stmt->bindValue(':p',$pwd,SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        if ($row){
            $_SESSION['user_id'] = $row['id'];
            header('Location: ?'); exit;
        }else{
            $error = 'Senha incorreta.';
        }
    }
    show_login($error ?? '');
}

function handle_logout() {
    session_destroy();
    header('Location: ?');
    exit;
}

function handle_list() {
    if (!isset($_SESSION['user_id'])){ 
        http_response_code(401); 
        echo json_encode(['error'=>'not_logged']); 
        exit; 
    }
    $db = open_db();
    $stmt = $db->prepare('SELECT id,title,updated_at FROM notes WHERE user_id=:uid ORDER BY updated_at DESC');
    $stmt->bindValue(':uid',$_SESSION['user_id'],SQLITE3_INTEGER);
    $res = $stmt->execute();
    $list = [];
    while($row = $res->fetchArray(SQLITE3_ASSOC)) {
        // Formatar data para algo mais legível no iOS 6
        $row['updated_at'] = format_date($row['updated_at']);
        $list[] = $row;
    }
    
    // Headers específicos para iOS 6
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode($list);
    exit;
}

function handle_load() {
    if (!isset($_SESSION['user_id'])){ 
        http_response_code(401); 
        echo json_encode(['error'=>'not_logged']); 
        exit; 
    }
    $note_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $db = open_db();
    if ($note_id>0){
        $stmt = $db->prepare('SELECT id,title,content,updated_at FROM notes WHERE id=:id AND user_id=:uid');
        $stmt->bindValue(':id',$note_id,SQLITE3_INTEGER);
        $stmt->bindValue(':uid',$_SESSION['user_id'],SQLITE3_INTEGER);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        echo json_encode($row ? $row : []);
    }else{
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error'=>'no_id']);
    }
    exit;
}

function handle_save() {
    if (!isset($_SESSION['user_id'])){ 
        http_response_code(401); 
        echo json_encode(['error'=>'not_logged']); 
        exit; 
    }
    
    // Ler input de forma compatível
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Fallback para form data se JSON falhar
    if (!$data && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = $_POST;
    }
    
    if (!$data) { 
        http_response_code(400); 
        echo json_encode(['error'=>'no_data']); 
        exit; 
    }
    
    $title = isset($data['title']) ? $data['title'] : 'Sem título';
    $content = isset($data['content']) ? $data['content'] : '';
    $note_id = isset($data['id']) ? intval($data['id']) : 0;
    $now = date('c');
    $db = open_db();
    
    if ($note_id > 0){
        $stmt = $db->prepare('UPDATE notes SET title=:t, content=:c, updated_at=:u WHERE id=:id AND user_id=:uid');
        $stmt->bindValue(':t',$title,SQLITE3_TEXT);
        $stmt->bindValue(':c',$content,SQLITE3_TEXT);
        $stmt->bindValue(':u',$now,SQLITE3_TEXT);
        $stmt->bindValue(':id',$note_id,SQLITE3_INTEGER);
        $stmt->bindValue(':uid',$_SESSION['user_id'],SQLITE3_INTEGER);
        $res = $stmt->execute();
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status'=>'ok','id'=>$note_id,'updated_at'=>format_date($now)]);
    }else{
        $stmt = $db->prepare('INSERT INTO notes (user_id,title,content,updated_at) VALUES (:uid,:t,:c,:u)');
        $stmt->bindValue(':uid',$_SESSION['user_id'],SQLITE3_INTEGER);
        $stmt->bindValue(':t',$title,SQLITE3_TEXT);
        $stmt->bindValue(':c',$content,SQLITE3_TEXT);
        $stmt->bindValue(':u',$now,SQLITE3_TEXT);
        $res = $stmt->execute();
        $id = $db->lastInsertRowID();
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status'=>'ok','id'=>$id,'updated_at'=>format_date($now)]);
    }
    exit;
}

function handle_delete() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['status'=>'error','message'=>'not logged']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    // Fallback para form data
    if (!$data && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = $_POST;
    }
    
    $id = isset($data['id']) ? intval($data['id']) : 0;

    if ($id > 0) {
        $db = open_db();
        $stmt = $db->prepare('DELETE FROM notes WHERE id = :id AND user_id = :uid');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->execute();
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status'=>'ok']);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status'=>'error','message'=>'invalid id']);
    }
    exit;
}

function format_date($date_string) {
    $date = new DateTime($date_string);
    return $date->format('d/m/Y H:i');
}

function show_login($error = '') {
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta http-equiv="Content-Security-Policy" content="connect-src 'self'; font-src 'self'; frame-src 'self'; object-src 'none'; prefetch-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <meta name="format-detection" content="telephone=no">
  <meta name="robots" content="noindex,nofollow,noimageindex,noarchive,nosnippet,nocache,nositelinkssearchbox,nopagereadaloud,notranslate,noodp,noydir,noyaca">
  <meta name="googlebot" content="none,noindex,nofollow,noimageindex,noarchive,nosnippet,nocache,nositelinkssearchbox,nopagereadaloud,notranslate,noodp,noydir,noyaca">
  <title>nTexto</title>
  <meta charset="utf-8">
  <meta name="apple-mobile-web-app-title" content="nTexto">
  <link rel="stylesheet" href="assets/css/style.css" type="text/css">
  <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
<link rel="manifest" href="site.webmanifest">
  <style>
body{margin:10px; font-size:16px; -webkit-text-size-adjust: 100%; }
input, button { font-size:16px; -webkit-appearance: none; border-radius:0; }
button { padding:8px 12px; background:#f0f0f0; border:1px solid #ccc; }
  </style>

</head>
<body class="login">
<div>
<img src="assets/img/nTexto.png" alt="nTexto">
<?php if(!empty($error)) echo '<p style="color:red">'.htmlspecialchars($error).'</p>'; ?>
<form method="post" action="?action=login">
    <input type="password" name="password">
    <button type="submit">login</button>
</form>
<br>
<small><a href="https://github.com/robertostrabelli/ntexto">https://github.com/robertostrabelli/ntexto</a></small>
</div>
</body></html>
    <?php
    exit;
}

function show_editor() {
    // Inicializar banco se necessário
    if (!file_exists(DB_FILE)) {
        init_db();
    }
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta http-equiv="Content-Security-Policy" content="connect-src 'self'; font-src 'self'; frame-src 'self'; object-src 'none'; prefetch-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <meta name="format-detection" content="telephone=no">
  <meta name="robots" content="noindex,nofollow,noimageindex,noarchive,nosnippet,nocache,nositelinkssearchbox,nopagereadaloud,notranslate,noodp,noydir,noyaca">
  <meta name="googlebot" content="none,noindex,nofollow,noimageindex,noarchive,nosnippet,nocache,nositelinkssearchbox,nopagereadaloud,notranslate,noodp,noydir,noyaca">
  <title>nTexto</title>
  <meta charset="utf-8">
  <meta name="apple-mobile-web-app-title" content="nTexto">
  <link rel="stylesheet" href="assets/css/style.css" type="text/css">
  <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
<link rel="manifest" href="site.webmanifest">
<style>
    @media print {
    body * {
        display: none !important;
    }
    #conteudo, #conteudo * {
        display: block !important;color:darkgray;
    }
    input,textarea {border:0;}
}
</style>
</head><body>
<div id="toolbar" class="menulink">
    <button><img src="assets/img/nTexto.png" alt="nTexto"></button>
    <button id="newBtn"><img src="assets/img/new.png" alt="Novo"></button>
    <button id="saveBtn"><img src="assets/img/save.png" alt="Salvar"></button>
    <button id="but_print"><img src="assets/img/print.png" alt="Imprimir"></button>
    <button id="deleteBtn"><img src="assets/img/delete.png" alt="Excluir"></button>
    <button id="logout"><img src="assets/img/exit.png" alt="Sair"></button>
    <span id="status"></span>
</div>
<div id="notesList">Carregando...</div>
<div id="conteudo">
    <input id="title" placeholder="Título" style="width:100%; margin:6px 0;" />
<textarea id="editor" placeholder="Escreva aqui..."></textarea>
</div>
<script>
// Polyfill para Promise se necessário
if (typeof Promise === 'undefined') {
    var Promise = function(executor) {
        var self = this;
        this._callbacks = [];
        this._state = 'pending';
        
        function resolve(value) {
            if (self._state !== 'pending') return;
            self._state = 'fulfilled';
            self._value = value;
            self._callbacks.forEach(function(cb) {
                setTimeout(function() {
                    cb.onFulfilled && cb.onFulfilled(value);
                }, 0);
            });
        }
        
        function reject(reason) {
            if (self._state !== 'pending') return;
            self._state = 'rejected';
            self._value = reason;
            self._callbacks.forEach(function(cb) {
                setTimeout(function() {
                    cb.onRejected && cb.onRejected(reason);
                }, 0);
            });
        }
        
        try {
            executor(resolve, reject);
        } catch (e) {
            reject(e);
        }
    };
    
    Promise.prototype.then = function(onFulfilled, onRejected) {
        var self = this;
        return new Promise(function(resolve, reject) {
            function handle() {
                var callback = self._state === 'fulfilled' ? onFulfilled : onRejected;
                if (typeof callback === 'function') {
                    try {
                        var result = callback(self._value);
                        if (result instanceof Promise) {
                            result.then(resolve, reject);
                        } else {
                            resolve(result);
                        }
                    } catch (e) {
                        reject(e);
                    }
                } else {
                    (self._state === 'fulfilled' ? resolve : reject)(self._value);
                }
            }
            
            if (self._state !== 'pending') {
                setTimeout(handle, 0);
            } else {
                self._callbacks.push({
                    onFulfilled: function(value) {
                        try {
                            if (typeof onFulfilled === 'function') {
                                var result = onFulfilled(value);
                                resolve(result);
                            } else {
                                resolve(value);
                            }
                        } catch (e) {
                            reject(e);
                        }
                    },
                    onRejected: function(reason) {
                        try {
                            if (typeof onRejected === 'function') {
                                var result = onRejected(reason);
                                resolve(result);
                            } else {
                                reject(reason);
                            }
                        } catch (e) {
                            reject(e);
                        }
                    }
                });
            }
        });
    };
    
    Promise.prototype.catch = function(onRejected) {
        return this.then(null, onRejected);
    };
    
    window.Promise = Promise;
}

(function(){
    var currentId = 0;
    var saving = false;
    var saveTimer = null;
    var status = document.getElementById('status');

    function setStatus(t){ 
        status.textContent = t; 
        // Limpar status após delay
        if (t && t !== '') {
            setTimeout(function() {
                if (status.textContent === t) {
                    status.textContent = '';
                }
            }, 3000);
        }
    }

    function fetchJSON(url, opts){
        // Usar XMLHttpRequest para melhor compatibilidade
        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open(opts && opts.method ? opts.method : 'GET', url);
            xhr.setRequestHeader('Content-Type', 'application/json; charset=utf-8');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        resolve(data);
                    } catch (e) {
                        reject(e);
                    }
                } else {
                    reject(new Error('Request failed: ' + xhr.status));
                }
            };
            xhr.onerror = function() { reject(new Error('Network error')); };
            
            if (opts && opts.body) {
                xhr.send(opts.body);
            } else {
                xhr.send();
            }
        });
    }

    function loadList(){
        fetchJSON('?action=list').then(function(list){
            var html = '';
            if (list && list.length > 0) {
                list.forEach(function(it){ 
                    html += '<a href="#" data-id="'+it.id+'">'+(it.title||'Sem título')+' <small>('+it.updated_at+')</small></a><br>'; 
                });
            } else {
                html = 'Nenhuma nota encontrada';
            }
            document.getElementById('notesList').innerHTML = html;
            
            // Adicionar event listeners manualmente
            var links = document.querySelectorAll('#notesList a');
            for (var i = 0; i < links.length; i++) {
                links[i].addEventListener('click', function(e){
                    e.preventDefault(); 
                    loadNote(this.getAttribute('data-id'));
                });
            }
        }).catch(function(err){ 
            console.error('Erro ao listar:', err);
            setStatus('Erro ao listar'); 
        });
    }

    function loadNote(id){
        fetchJSON('?action=load&id='+encodeURIComponent(id)).then(function(n){
            if(n && n.id){ 
                currentId = n.id; 
                document.getElementById('title').value = n.title || ''; 
                document.getElementById('editor').value = n.content || ''; 
                setStatus('Carregado'); 
            }
        }).catch(function(err){ 
            console.error('Erro ao carregar:', err);
            setStatus('Erro ao carregar'); 
        });
    }

    function autosave(){
        if (saving) return;
        
        var title = document.getElementById('title').value;
        var content = document.getElementById('editor').value;
        saving = true; 
        setStatus('Salvando...');
        
        fetchJSON('?action=save', {
            method: 'POST',
            body: JSON.stringify({id: currentId, title: title, content: content})
        }).then(function(res){
            if(res && res.status=='ok'){ 
                currentId = res.id; 
                setStatus('Salvo'); 
                loadList(); 
            } else {
                setStatus('Erro ao salvar');
            }
            saving = false;
        }).catch(function(err){ 
            console.error('Erro ao salvar:', err);
            setStatus('Erro ao salvar'); 
            saving = false; 
        });
    }

    function scheduleSave(){
        if(saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(autosave, 3000); // salva 3s depois da última digitação
    }

    function deleteNote(){
        if(!currentId){ setStatus('Nenhuma nota selecionada'); return; }
        if(!confirm('Apagar esta nota?')) return;
        
        fetchJSON('?action=delete', {
            method: 'POST',
            body: JSON.stringify({id: currentId})
        }).then(function(res){
            if(res && res.status=='ok'){
                setStatus('Nota apagada');
                currentId = 0;
                document.getElementById('title').value='';
                document.getElementById('editor').value='';
                loadList();
            } else setStatus('Erro ao apagar');
        }).catch(function(err){ 
            console.error('Erro ao apagar:', err);
            setStatus('Erro ao apagar'); 
        });
    }

    document.getElementById('editor').addEventListener('input', scheduleSave);
    document.getElementById('title').addEventListener('input', scheduleSave);

    document.getElementById('newBtn').addEventListener('click', function(){ 
        currentId = 0; 
        document.getElementById('title').value=''; 
        document.getElementById('editor').value=''; 
        setStatus('Nova nota'); 
    });
    document.getElementById('saveBtn').addEventListener('click', autosave);

    document.getElementById("but_print").addEventListener("click", function(e) {
                e.preventDefault();
                window.print();
            });
    document.getElementById('deleteBtn').addEventListener('click', deleteNote);
    document.getElementById('logout').addEventListener('click', function(){ location.href='?action=logout'; });

    // Backup periódico a cada 15s se houver mudanças
    setInterval(function(){ 
        if(!saving && (document.getElementById('title').value || document.getElementById('editor').value)) {
            scheduleSave(); 
        }
    }, 15000);

    // Carregar lista inicial
    loadList();
})();
</script>
</body></html>
    <?php
    exit;
}
?>