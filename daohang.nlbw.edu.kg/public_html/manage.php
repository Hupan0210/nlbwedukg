<?php
// 设置错误报告，方便调试（生产环境中可关闭）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置响应头，允许跨域请求，适配前端 AJAX 调用
header('Access-Control-Allow-Origin: https://daohang.nlbw.edu.kg');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// 连接 SQLite 数据库
try {
    $db = new PDO('sqlite:navigation.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // 确保数据库编码为 UTF-8
    $db->exec('PRAGMA encoding = "UTF-8"');
} catch (PDOException $e) {
    // 数据库连接失败时返回错误 JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => '数据库连接失败: ' . $e->getMessage()]);
    exit;
}

// 处理 API 请求
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // 获取分类列表，按 id 升序排序
    if ($_GET['action'] === 'categories') {
        $stmt = $db->query('SELECT * FROM categories ORDER BY id ASC');
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
        exit;
    }
    
    // 获取链接列表，按 id 升序排序
    if ($_GET['action'] === 'links') {
        $stmt = $db->query('SELECT * FROM links ORDER BY id ASC');
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
        exit;
    }
}

// 初始化消息变量，用于显示操作结果
$message = '';

// 处理表单提交（增删改）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 添加分类
    if ($_POST['action'] === 'add_category') {
        $name = trim($_POST['name']);
        if (!empty($name)) {
            $stmt = $db->prepare('INSERT INTO categories (name) VALUES (?)');
            $stmt->execute([$name]);
            $message = '分类添加成功！';
        } else {
            $message = '分类名称不能为空！';
        }
        header('Location: manage.php?message=' . urlencode($message));
        exit;
    }
    
    // 添加链接
    if ($_POST['action'] === 'add_link') {
        $category_id = $_POST['category_id'];
        $name = trim($_POST['name']);
        $url = trim($_POST['url']);
        // 验证 icon 是否为有效的 Font Awesome 类，默认为 fas fa-link
        $icon = !empty($_POST['icon']) && preg_match('/^fa[s|r|b] fa-\w+$/', $_POST['icon']) 
                ? $_POST['icon'] : 'fas fa-link';
        if (!empty($name) && !empty($url) && !empty($category_id)) {
            $stmt = $db->prepare('INSERT INTO links (category_id, name, url, icon) VALUES (?, ?, ?, ?)');
            $stmt->execute([$category_id, $name, $url, $icon]);
            $message = '链接添加成功！';
        } else {
            $message = '链接名称、URL 或分类不能为空！';
        }
        header('Location: manage.php?message=' . urlencode($message));
        exit;
    }
    
    // 编辑分类
    if ($_POST['action'] === 'edit_category') {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        if (!empty($id) && !empty($name)) {
            $stmt = $db->prepare('UPDATE categories SET name = ? WHERE id = ?');
            $stmt->execute([$name, $id]);
            $message = '分类编辑成功！';
        } else {
            $message = '分类名称或 ID 不能为空！';
        }
        header('Location: manage.php?message=' . urlencode($message));
        exit;
    }
    
    // 编辑链接
    if ($_POST['action'] === 'edit_link') {
        $id = $_POST['id'];
        $category_id = $_POST['category_id'];
        $name = trim($_POST['name']);
        $url = trim($_POST['url']);
        // 验证 icon 是否为有效的 Font Awesome 类，默认为 fas fa-link
        $icon = !empty($_POST['icon']) && preg_match('/^fa[s|r|b] fa-\w+$/', $_POST['icon']) 
                ? $_POST['icon'] : 'fas fa-link';
        if (!empty($id) && !empty($name) && !empty($url) && !empty($category_id)) {
            $stmt = $db->prepare('UPDATE links SET category_id = ?, name = ?, url = ?, icon = ? WHERE id = ?');
            $stmt->execute([$category_id, $name, $url, $icon, $id]);
            $message = '链接编辑成功！';
        } else {
            $message = '链接名称、URL、分类或 ID 不能为空！';
        }
        header('Location: manage.php?message=' . urlencode($message));
        exit;
    }
    
    // 删除分类（同时删除关联链接）
    if ($_POST['action'] === 'delete_category') {
        $id = $_POST['id'];
        if (!empty($id)) {
            $db->beginTransaction();
            $stmt = $db->prepare('DELETE FROM links WHERE category_id = ?');
            $stmt->execute([$id]);
            $stmt = $db->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            $db->commit();
            $message = '分类及其关联链接删除成功！';
        } else {
            $message = '分类 ID 不能为空！';
        }
        header('Location: manage.php?message=' . urlencode($message));
        exit;
    }
    
    // 删除链接（通过 ID）
    if ($_POST['action'] === 'delete_link') {
        $id = $_POST['id'];
        if (!empty($id)) {
            $stmt = $db->prepare('DELETE FROM links WHERE id = ?');
            $stmt->execute([$id]);
            $message = '链接删除成功！';
        } else {
            $message = '链接 ID 不能为空！';
        }
        header('Location: manage.php?message=' . urlencode($message));
        exit;
    }
    
    // 删除链接（通过 URL）
    if ($_POST['action'] === 'delete_link_by_url') {
        $url = trim($_POST['url']);
        if (!empty($url)) {
            $stmt = $db->prepare('SELECT id, name FROM links WHERE url = ?');
            $stmt->execute([$url]);
            $matching_links = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($matching_links) > 1) {
                // 多个链接匹配，提示用户选择具体链接
                $message = '找到多个匹配的链接，请在链接列表中删除具体链接！';
            } elseif (count($matching_links) === 1) {
                // 唯一匹配，删除链接
                $stmt = $db->prepare('DELETE FROM links WHERE url = ?');
                $stmt->execute([$url]);
                $message = "链接 '{$matching_links[0]['name']}' 删除成功！";
            } else {
                // 无匹配链接
                $message = '未找到匹配的链接！';
            }
        } else {
            $message = '请输入有效的 URL！';
        }
        header('Location: manage.php?message=' . urlencode($message));
        exit;
    }
}

// 获取分类列表，用于下拉菜单
$categories = $db->query('SELECT * FROM categories ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
// 获取链接列表，用于显示和删除下拉菜单
$links = $db->query('SELECT l.*, c.name as category_name FROM links l JOIN categories c ON l.category_id = c.id ORDER BY l.id ASC')->fetchAll(PDO::FETCH_ASSOC);
// 获取所有唯一的 URL，用于删除下拉菜单
$unique_urls = $db->query('SELECT DISTINCT url FROM links ORDER BY url ASC')->fetchAll(PDO::FETCH_COLUMN);

// 获取提示消息
$message = isset($_GET['message']) ? urldecode($_GET['message']) : '';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>导航管理 - 南来北往</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: #f4f4f4;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
        }
        form {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin: 10px 0 5px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            padding: 10px 20px;
            background: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
        button.delete {
            background: #dc3545;
        }
        button.delete:hover {
            background: #b02a37;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background: #007bff;
            color: #fff;
        }
        .actions {
            display: flex;
            gap: 10px;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>导航管理 - 南来北往</h1>
        
        <!-- 显示操作提示消息 -->
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '成功') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- 添加分类 -->
        <h2>添加分类</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_category">
            <label>分类名称</label>
            <input type="text" name="name" required>
            <button type="submit">添加</button>
        </form>
        
        <!-- 添加链接 -->
        <h2>添加链接</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_link">
            <label>分类</label>
            <select name="category_id" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['id']); ?>">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>链接名称</label>
            <input type="text" name="name" required>
            <label>链接 URL</label>
            <input type="url" name="url" required>
            <label>图标 (Font Awesome 类，如 fas fa-link)</label>
            <input type="text" name="icon" placeholder="fas fa-link">
            <button type="submit">添加</button>
        </form>
        
        <!-- 删除链接（通过 URL） -->
        <h2>删除链接</h2>
        <form method="POST" onsubmit="return confirm('确定删除此链接？');">
            <input type="hidden" name="action" value="delete_link_by_url">
            <label>选择或输入要删除的链接 URL</label>
            <select name="url" onchange="this.form.querySelector('input[name=url]').value = this.value;">
                <option value="">-- 选择 URL --</option>
                <?php foreach ($unique_urls as $url): ?>
                    <option value="<?php echo htmlspecialchars($url); ?>">
                        <?php echo htmlspecialchars($url); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="url" name="url" placeholder="输入 URL（如 https://daohang.nlbw.edu.kg/manage.php）" required>
            <button type="submit" class="delete">删除</button>
        </form>
        
        <!-- 分类列表 -->
        <h2>分类列表</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>名称</th>
                <th>操作</th>
            </tr>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?php echo htmlspecialchars($cat['id']); ?></td>
                    <td><?php echo htmlspecialchars($cat['name']); ?></td>
                    <td class="actions">
                        <!-- 编辑分类 -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="edit_category">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($cat['id']); ?>">
                            <input type="text" name="name" value="<?php echo htmlspecialchars($cat['name']); ?>" required>
                            <button type="submit">保存</button>
                        </form>
                        <!-- 删除分类 -->
                        <form method="POST" style="display:inline;" onsubmit="return confirm('确定删除此分类及其所有链接？');">
                            <input type="hidden" name="action" value="delete_category">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($cat['id']); ?>">
                            <button type="submit">删除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        
        <!-- 链接列表 -->
        <h2>链接列表</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>分类</th>
                <th>名称</th>
                <th>URL</th>
                <th>图标</th>
                <th>操作</th>
            </tr>
            <?php foreach ($links as $link): ?>
                <tr>
                    <td><?php echo htmlspecialchars($link['id']); ?></td>
                    <td><?php echo htmlspecialchars($link['category_name']); ?></td>
                    <td><?php echo htmlspecialchars($link['name']); ?></td>
                    <td><a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank"><?php echo htmlspecialchars($link['url']); ?></a></td>
                    <td><?php echo htmlspecialchars($link['icon']); ?></td>
                    <td class="actions">
                        <!-- 编辑链接 -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="edit_link">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($link['id']); ?>">
                            <select name="category_id" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['id']); ?>" <?php echo $cat['id'] == $link['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($link['name']); ?>" required>
                            <input type="url" name="url" value="<?php echo htmlspecialchars($link['url']); ?>" required>
                            <input type="text" name="icon" value="<?php echo htmlspecialchars($link['icon']); ?>" placeholder="fas fa-link">
                            <button type="submit">保存</button>
                        </form>
                        <!-- 删除链接 -->
                        <form method="POST" style="display:inline;" onsubmit="return confirm('确定删除此链接？');">
                            <input type="hidden" name="action" value="delete_link">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($link['id']); ?>">
                            <button type="submit">删除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>