<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';


$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT *
    FROM board_posts
    WHERE id = :id
");
$stmt->execute([':id' => $post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
    echo "指定された投稿が見つかりません。";
    exit;
}

$errors = [];

$author = $post['author'];
$title = $post['title'];
$location = $post['location'];
$body = $post['body'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $author = trim($_POST['author'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if ($body === '') {
        $errors[] = '内容を入力してください。';
    }

    if (count($errors) === 0) {
        try {
            $now = date('Y-m-d H:i:s');

            $stmt = $pdo->prepare("
                UPDATE board_posts
                SET
                    author = :author,
                    title = :title,
                    location = :location,
                    body = :body,
                    edited_at = :edited_at
                WHERE id = :id
            ");
            $stmt->execute([
                ':author' => $author,
                ':title' => $title,
                ':location' => $location,
                ':body' => $body,
                ':edited_at' => $now,
                ':id' => $post_id,
            ]);

            header('Location: board.php');
            exit;
        } catch (Exception $e) {
            $errors[] = '更新に失敗しました: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>掲示板投稿編集 - <?php echo h(system_name()); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>掲示板投稿編集</h1>

<div class="menu">
    <a href="board.php">掲示板へ戻る</a>
    <a href="index.php">一覧へ戻る</a>
</div>

<?php if (count($errors) > 0): ?>
    <div class="errors">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo h($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="edit_board_post.php?id=<?php echo h($post_id); ?>">
    <div class="row">
        <label>投稿者</label>
        <input type="text" name="author" value="<?php echo h($author); ?>">
    </div>

    <div class="row">
        <label>タイトル</label>
        <input type="text" name="title" value="<?php echo h($title); ?>">
    </div>

    <div class="row">
        <label>場所</label>
        <input type="text" name="location" value="<?php echo h($location); ?>">
    </div>

    <div class="row">
        <label>内容 *</label>
        <textarea name="body"><?php echo h($body); ?></textarea>
    </div>

    <button type="submit">更新</button>
</form>

</body>
</html>
