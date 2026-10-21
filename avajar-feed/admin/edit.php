<?php
declare(strict_types=1);

require __DIR__ . '/../src/admin.php';

admin_start();
admin_require_login();

$fields = [
    'slug' => '',
    'title' => '',
    'caption' => '',
    'poster_url' => '',
    'video_360_url' => '',
    'video_540_url' => '',
    'video_720_url' => '',
    'duration_seconds' => '',
    'sort_order' => '0',
];

$idParam = $_POST['id'] ?? $_GET['id'] ?? '';
$id = $idParam === '' ? null : filter_var($idParam, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    http_response_code(404);
    exit('Not found');
}

$values = $fields;
if ($id !== null) {
    $stmt = db()->prepare('SELECT * FROM reels WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if ($row === false) {
        http_response_code(404);
        exit('Not found');
    }
    foreach ($fields as $key => $_) {
        $values[$key] = (string) $row[$key];
    }
}

$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    foreach ($fields as $key => $_) {
        $values[$key] = trim(str_replace("\r\n", "\n", (string) ($_POST[$key] ?? '')));
    }

    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $values['slug']) || strlen($values['slug']) > 100) {
        $errors[] = 'slug فقط حروف کوچک انگلیسی، عدد و خط تیره (حداکثر ۱۰۰ نویسه).';
    }
    if ($values['title'] === '' || str_length($values['title']) > 150) {
        $errors[] = 'عنوان الزامی است (حداکثر ۱۵۰ نویسه).';
    }
    if (str_length($values['caption']) > 2200) {
        $errors[] = 'کپشن حداکثر ۲۲۰۰ نویسه.';
    }
    $urlLabels = [
        'poster_url' => 'آدرس پوستر',
        'video_360_url' => 'آدرس ویدیو 360p',
        'video_540_url' => 'آدرس ویدیو 540p',
        'video_720_url' => 'آدرس ویدیو 720p',
    ];
    foreach ($urlLabels as $key => $label) {
        if (!valid_media_ref($values[$key])) {
            $errors[] = $label . ' معتبر نیست (آدرس http(s) یا مسیر نسبی روی CDN).';
        }
    }
    $duration = filter_var($values['duration_seconds'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3600]]);
    if ($duration === false) {
        $errors[] = 'مدت باید عدد صحیح بین ۱ تا ۳۶۰۰ ثانیه باشد.';
    }
    $sort = filter_var($values['sort_order'], FILTER_VALIDATE_INT, ['options' => ['min_range' => -999999, 'max_range' => 999999]]);
    if ($sort === false) {
        $errors[] = 'ترتیب باید عدد صحیح باشد.';
    }

    if (!$errors) {
        $params = [
            ':slug' => $values['slug'],
            ':title' => $values['title'],
            ':caption' => $values['caption'],
            ':poster_url' => $values['poster_url'],
            ':v360' => $values['video_360_url'],
            ':v540' => $values['video_540_url'],
            ':v720' => $values['video_720_url'],
            ':duration' => $duration,
            ':sort' => $sort,
        ];
        try {
            if ($id === null) {
                $stmt = db()->prepare('INSERT INTO reels (slug, title, caption, poster_url, video_360_url, video_540_url, video_720_url, duration_seconds, sort_order)
                    VALUES (:slug, :title, :caption, :poster_url, :v360, :v540, :v720, :duration, :sort)');
                $stmt->execute($params);
                flash('ریلز ذخیره شد.');
            } else {
                $stmt = db()->prepare("UPDATE reels SET slug = :slug, title = :title, caption = :caption, poster_url = :poster_url,
                    video_360_url = :v360, video_540_url = :v540, video_720_url = :v720, duration_seconds = :duration,
                    sort_order = :sort, updated_at = strftime('%Y-%m-%dT%H:%M:%SZ', 'now') WHERE id = :id");
                $stmt->execute($params + [':id' => $id]);
                flash('تغییرات ذخیره شد.');
            }
            redirect('index.php');
        } catch (PDOException $ex) {
            if (str_contains($ex->getMessage(), 'UNIQUE')) {
                $errors[] = 'این slug قبلاً استفاده شده است.';
            } else {
                throw $ex;
            }
        }
    }
}

admin_header($id === null ? 'ریلز جدید' : 'ویرایش ریلز');
?>
<h1><?= $id === null ? 'ریلز جدید' : 'ویرایش ریلز' ?></h1>
<?php if ($errors): ?>
<div class="errors"><ul>
<?php foreach ($errors as $err): ?>
  <li><?= e($err) ?></li>
<?php endforeach; ?>
</ul></div>
<?php endif; ?>
<form method="post" action="edit.php">
  <?= csrf_field() ?>
  <?php if ($id !== null): ?>
  <input type="hidden" name="id" value="<?= e((string) $id) ?>">
  <?php endif; ?>

  <label for="slug">slug</label>
  <input id="slug" name="slug" type="text" dir="ltr" required maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= e($values['slug']) ?>">
  <small>مثلاً brand-story-cafe — در آدرس صفحه استفاده می‌شود.</small>

  <label for="title">عنوان</label>
  <input id="title" name="title" type="text" required maxlength="150" value="<?= e($values['title']) ?>">

  <label for="caption">کپشن</label>
  <textarea id="caption" name="caption" maxlength="2200"><?= e($values['caption']) ?></textarea>

  <label for="poster_url">آدرس پوستر (WebP)</label>
  <input id="poster_url" name="poster_url" type="text" dir="ltr" required maxlength="500" value="<?= e($values['poster_url']) ?>">

  <label for="video_360_url">آدرس ویدیو 360p</label>
  <input id="video_360_url" name="video_360_url" type="text" dir="ltr" required maxlength="500" value="<?= e($values['video_360_url']) ?>">

  <label for="video_540_url">آدرس ویدیو 540p</label>
  <input id="video_540_url" name="video_540_url" type="text" dir="ltr" required maxlength="500" value="<?= e($values['video_540_url']) ?>">

  <label for="video_720_url">آدرس ویدیو 720p</label>
  <input id="video_720_url" name="video_720_url" type="text" dir="ltr" required maxlength="500" value="<?= e($values['video_720_url']) ?>">
  <small>آدرس کامل https:// یا مسیر نسبی نسبت به CDN_BASE_URL، مثل reels/my-reel/720.mp4</small>

  <label for="duration_seconds">مدت (ثانیه)</label>
  <input id="duration_seconds" name="duration_seconds" type="number" min="1" max="3600" required value="<?= e($values['duration_seconds']) ?>">

  <label for="sort_order">ترتیب نمایش (کوچک‌تر = بالاتر)</label>
  <input id="sort_order" name="sort_order" type="number" required value="<?= e($values['sort_order']) ?>">

  <p><button type="submit">ذخیره</button> <a href="index.php">انصراف</a></p>
</form>
<?php
admin_footer();
