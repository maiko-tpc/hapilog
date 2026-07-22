<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$errors = [];

$author = '';
$title = '';
$body = '';
$location = '';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

/*
 * 新規投稿
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $author = trim($_POST['author'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $location = trim($_POST['location'] ?? '');

    if ($body === '') {
        $errors[] = '内容を入力してください。';
    }

    if (count($errors) === 0) {
        try {
            $now = date('Y-m-d H:i:s');

            $stmt = $pdo->prepare("
                INSERT INTO board_posts
                    (
                        author,
                        title,
                        body,
                        location,
                        created_at
                    )
                VALUES
                    (
                        :author,
                        :title,
                        :body,
                        :location,
                        :created_at
                    )
            ");

            $stmt->execute([
                ':author' => $author,
                ':title' => $title,
                ':body' => $body,
                ':location' => $location,
                ':created_at' => $now,
            ]);

            header('Location: board.php');
            exit;

        } catch (Exception $e) {
            $errors[] = '投稿に失敗しました: ' . $e->getMessage();
        }
    }
}

/*
 * 掲示板サマリー
 *
 * 検索条件とは無関係に、すべての投稿を集計する。
 *
 * 最終更新日時は、各投稿の
 * created_at、edited_at、returned_at のうち
 * 最も新しい日時から求める。
 */
$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total_count,

        SUM(
            CASE
                WHEN TRIM(COALESCE(returned_at, '')) <> ''
                THEN 1
                ELSE 0
            END
        ) AS returned_count,

        SUM(
            CASE
                WHEN TRIM(COALESCE(returned_at, '')) = ''
                THEN 1
                ELSE 0
            END
        ) AS open_count,

        MAX(
            CASE
                WHEN
                    COALESCE(edited_at, '') >= COALESCE(returned_at, '')
                    AND
                    COALESCE(edited_at, '') >= COALESCE(created_at, '')
                THEN edited_at

                WHEN
                    COALESCE(returned_at, '') >= COALESCE(created_at, '')
                THEN returned_at

                ELSE created_at
            END
        ) AS last_updated_at

    FROM board_posts
");

$summary_result = $stmt->fetch(PDO::FETCH_ASSOC);

$board_summary = [
    'total_count' => (int)($summary_result['total_count'] ?? 0),
    'returned_count' => (int)($summary_result['returned_count'] ?? 0),
    'open_count' => (int)($summary_result['open_count'] ?? 0),
    'last_updated_at' => trim(
        (string)($summary_result['last_updated_at'] ?? '')
    ),
];

/*
 * 表示用の最終更新日時
 *
 * DBには秒まで保存されているが、
 * 画面では YYYY-MM-DD HH:MM まで表示する。
 */
$last_updated_display = '';

if ($board_summary['last_updated_at'] !== '') {
    $last_updated_timestamp = strtotime(
        $board_summary['last_updated_at']
    );

    if ($last_updated_timestamp !== false) {
        $last_updated_display = date(
            'Y-m-d H:i',
            $last_updated_timestamp
        );
    }
}

/*
 * 投稿一覧
 */
$sql = "
    SELECT *
    FROM board_posts
";

$params = [];

if ($q !== '') {
    $sql .= "
        WHERE
            title LIKE :q
            OR author LIKE :q
            OR location LIKE :q
            OR body LIKE :q
    ";

    $params[':q'] = '%' . $q . '%';
}

$sql .= "
    ORDER BY created_at DESC, id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>掲示板 - <?php echo h(system_name()); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="page-header">
    <h1>掲示板</h1>

    <div class="compact-summary">
        <div>
            合計投稿 <?php echo h($board_summary['total_count']); ?>
            ／ 返却完了 <?php echo h($board_summary['returned_count']); ?>
            ／ 未返却 <?php echo h($board_summary['open_count']); ?>
        </div>

        <?php if ($last_updated_display !== ''): ?>
            <div>
                最終更新
                <?php echo h($last_updated_display); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="menu">
    <a href="index.php">一覧</a>
    <a href="new_item.php">新規登録</a>
    <a href="export_csv.php">CSV出力</a>
    <a href="board.php">掲示板</a>
</div>

<div class="box">
    <h2>新規投稿</h2>

    <?php if (count($errors) > 0): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo h($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="board.php">
        <div class="row">
            <label>投稿者</label>

            <input
                type="text"
                name="author"
                value="<?php echo h($author); ?>"
            >
        </div>

        <div class="row">
            <label>タイトル</label>

            <input
                type="text"
                name="title"
                value="<?php echo h($title); ?>"
                placeholder="例: LEMOケーブルの移動"
            >
        </div>

        <div class="row">
            <label>場所</label>

            <input
                type="text"
                name="location"
                value="<?php echo h($location); ?>"
                placeholder="例: RI棟 → 西実験室"
            >
        </div>

        <div class="row">
            <label>内容 *</label>

            <textarea
                name="body"
                placeholder="何をどこへ移動したか、誰が使っているか等"
            ><?php echo h($body); ?></textarea>
        </div>

        <button type="submit">投稿</button>
    </form>
</div>

<div class="box">
    <h2>投稿検索</h2>

    <form class="search" method="get" action="board.php">
        <label>
            検索：

            <input
                type="text"
                name="q"
                value="<?php echo h($q); ?>"
                placeholder="タイトル、投稿者、場所、内容"
            >
        </label>

        <button type="submit">検索</button>

        <?php if ($q !== ''): ?>
            <a href="board.php">検索クリア</a>
        <?php endif; ?>
    </form>
</div>

<div class="box">
    <h2>投稿一覧</h2>

    <?php if ($q !== ''): ?>
        <p>
            検索条件：
            <strong><?php echo h($q); ?></strong>
            ／ <?php echo h(count($posts)); ?> 件
        </p>
    <?php endif; ?>

    <?php if (count($posts) === 0): ?>
        <p>投稿はありません。</p>

    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <?php
            $is_returned =
                isset($post['returned_at'])
                && trim((string)$post['returned_at']) !== '';

            $is_edited =
                isset($post['edited_at'])
                && trim((string)$post['edited_at']) !== '';
            ?>

            <div class="board-post <?php echo $is_returned ? 'board-post-returned' : ''; ?>">
                <div class="board-post-header">
                    <strong>
                        No.<?php echo h($post['id']); ?>
                        &nbsp;
                        <?php echo h(
                            $post['title'] !== ''
                                ? $post['title']
                                : '無題'
                        ); ?>
                    </strong>

                    <?php if ($is_returned): ?>
                        <span class="return-badge">
                            返却完了
                        </span>
                    <?php else: ?>
                        <span class="return-badge return-badge-open">
                            未返却
                        </span>
                    <?php endif; ?>
                </div>

                <div class="board-post-meta">
                    <?php echo h($post['created_at']); ?>

                    <?php if ($post['author'] !== ''): ?>
                        ／ 投稿者:
                        <?php echo h($post['author']); ?>
                    <?php endif; ?>

                    <?php if ($post['location'] !== ''): ?>
                        ／ 場所:
                        <?php echo h($post['location']); ?>
                    <?php endif; ?>

                    <?php if ($is_returned): ?>
                        ／ 返却完了:
                        <?php echo h($post['returned_at']); ?>
                    <?php endif; ?>

                    <?php if ($is_edited): ?>
                        ／ 編集済み:
                        <?php echo h($post['edited_at']); ?>
                    <?php endif; ?>
                </div>

                <div class="board-post-body"><?php echo h($post['body']); ?></div>

                <div class="board-post-actions">
                    <a href="edit_board_post.php?id=<?php echo h($post['id']); ?>">
                        編集
                    </a>

                    <?php if (!$is_returned): ?>
                        <a
                            href="return_board_post.php?id=<?php echo h($post['id']); ?>"
                            onclick="return confirm('この投稿を返却完了にします。よろしいですか？');"
                        >
                            返却完了
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
