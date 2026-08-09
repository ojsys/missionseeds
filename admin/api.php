<?php
require_once __DIR__ . '/../includes/bootstrap.php';

start_session_safe();
header('Content-Type: application/json');

if (!admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in.']);
    exit;
}

// Every action here edits public site content.
if (!user_can('manage_content')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Your role cannot edit site content.']);
    exit;
}

if (!csrf_check($_POST['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired — please refresh the page.']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        case 'save_setting': {
            $key = $_POST['key'] ?? '';
            if (!is_inline_editable($key)) {
                throw new RuntimeException('That field cannot be edited inline.');
            }
            $value = trim((string) ($_POST['value'] ?? ''));
            set_setting($key, $value);
            audit('edit_copy', 'setting', null, $key);
            echo json_encode(['ok' => true]);
            break;
        }

        case 'save_item_field': {
            $id    = (int) ($_POST['id'] ?? 0);
            $field = $_POST['field'] ?? '';
            if (!in_array($field, ['title', 'body'], true)) {
                throw new RuntimeException('Invalid field.');
            }
            $item = get_list_item($id);
            if (!$item) throw new RuntimeException('Item not found.');
            $item[$field] = trim((string) ($_POST['value'] ?? ''));
            save_list_item($item);
            echo json_encode(['ok' => true]);
            break;
        }

        case 'add_item': {
            $group = $_POST['group_key'] ?? '';
            if (!in_array($group, ['criteria', 'receive'], true)) {
                throw new RuntimeException('Invalid group.');
            }
            $icon = $_POST['icon_key'] ?? 'seedling';
            if (!array_key_exists($icon, ICON_LIBRARY)) $icon = 'seedling';

            $id = save_list_item([
                'group_key'  => $group,
                'icon_key'   => $icon,
                'title'      => trim((string) ($_POST['title'] ?? '')),
                'body'       => trim((string) ($_POST['body'] ?? '')),
                'is_visible' => 1,
            ]);
            echo json_encode(['ok' => true, 'id' => $id]);
            break;
        }

        case 'delete_item': {
            $id = (int) ($_POST['id'] ?? 0);
            delete_list_item($id);
            echo json_encode(['ok' => true]);
            break;
        }

        case 'toggle_visible': {
            $id = (int) ($_POST['id'] ?? 0);
            $item = get_list_item($id);
            if (!$item) throw new RuntimeException('Item not found.');
            $item['is_visible'] = $item['is_visible'] ? 0 : 1;
            save_list_item($item);
            echo json_encode(['ok' => true]);
            break;
        }

        case 'move_item': {
            $id  = (int) ($_POST['id'] ?? 0);
            $dir = $_POST['direction'] ?? 'up';
            move_list_item($id, $dir === 'down' ? 'down' : 'up');
            echo json_encode(['ok' => true]);
            break;
        }

        case 'upload_image': {
            $target = $_POST['target'] ?? '';
            if (!in_array($target, ['hero_image'], true)) {
                throw new RuntimeException('Invalid upload target.');
            }
            if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('No file received.');
            }
            $file = $_FILES['image'];
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!isset($allowed[$mime])) {
                throw new RuntimeException('Please upload a JPG, PNG, or WEBP image.');
            }
            if ($file['size'] > 8 * 1024 * 1024) {
                throw new RuntimeException('Image must be under 8MB.');
            }
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

            $filename = $target . '-' . time() . '.' . $allowed[$mime];
            $dest     = UPLOAD_DIR . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                throw new RuntimeException('Could not save the uploaded file.');
            }

            $relative = 'assets/uploads/' . $filename;
            set_setting($target, $relative);
            echo json_encode(['ok' => true, 'url' => $relative]);
            break;
        }

        default:
            throw new RuntimeException('Unknown action.');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
