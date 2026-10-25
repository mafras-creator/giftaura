<?php
/** Admin - staff accounts. Variables: $accounts, $editing, $old, $errors, $newPassword. */
$checked = static fn(string $key): bool => in_array($key, $old['permissions'] ?? [], true);
?>
<p class="admin-lead">You are the <strong>main administrator</strong>. Create a staff account for each person who helps you,
    tick the areas they may work in, and give them the username and generated password privately. They must choose their own
    password the first time they log in. Only you can open Staff accounts, Settings, the E-mail log and the Activity log.</p>

<?php if ($newPassword !== null): ?>
    <div class="alert alert--success temp-password" role="status">
        <span>
            <?= $newPassword['new'] ? 'Staff account created' : 'New password generated' ?> for <strong><?= e($newPassword['name']) ?></strong>.<br>
            Username: <code class="temp-password__value"><?= e($newPassword['username']) ?></code>
            Password: <code class="temp-password__value"><?= e($newPassword['password']) ?></code><br>
            Give these to the staff member privately. They log in on the normal login page with the username, and must then choose
            their own password. The password is shown <strong>only this once</strong> and is not stored anywhere in readable form.
        </span>
    </div>
<?php endif; ?>

<div class="admin-columns admin-columns--wide-left">
    <section class="admin-panel">
        <header class="admin-panel__header"><h2 class="admin-panel__title">Administrators and staff</h2><span class="muted"><?= e((string) count($accounts)) ?></span></header>
        <div class="table-wrap">
            <table class="data-table data-table--compact">
                <thead><tr><th scope="col">Name</th><th scope="col">Login</th><th scope="col">Can work in</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($accounts as $a): ?>
                    <?php $isStaff = $a['role'] === 'staff'; $active = (int) $a['is_active'] === 1; ?>
                    <tr>
                        <td><strong><?= e($a['full_name']) ?></strong><br>
                            <small class="muted"><?= $isStaff ? 'Staff' : 'Main administrator' ?>
                            <?= $a['last_login_at'] !== null ? ' · last login ' . e(format_datetime($a['last_login_at'])) : ' · never logged in' ?></small></td>
                        <td><?= $a['username'] !== null ? e($a['username']) : '' ?><?= $a['username'] !== null && $a['email'] !== null ? '<br>' : '' ?><small class="muted"><?= e($a['email'] ?? '') ?></small></td>
                        <td><?php if ($isStaff): ?>
                                <?php foreach (StaffService::cleanPermissions($a['permissions']) as $p): ?>
                                    <span class="badge badge--neutral"><?= e(StaffService::PERMISSIONS[$p][0]) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?><span class="badge badge--success">Everything</span><?php endif; ?></td>
                        <td><?php if (!$active): ?><span class="badge badge--warning">Deactivated</span>
                            <?php elseif ((int) $a['must_change_password'] === 1): ?><span class="badge badge--warning">Must change password</span>
                            <?php else: ?><span class="badge badge--success">Active</span><?php endif; ?></td>
                        <td class="actions">
                            <?php if ($isStaff): ?>
                                <a class="btn btn--ghost btn--small" href="<?= e(url('admin/staff.php', ['edit' => $a['id']])) ?>">Edit</a>
                                <form method="post" action="<?= e(url('admin/staff.php')) ?>" data-confirm="Generate a new password for <?= e($a['full_name']) ?>? The old password stops working.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $a['id']) ?>">
                                    <input type="hidden" name="action" value="reset_password">
                                    <button type="submit" class="btn btn--ghost btn--small">New password</button>
                                </form>
                                <form method="post" action="<?= e(url('admin/staff.php')) ?>"<?= $active ? ' data-confirm="Deactivate ' . e($a['full_name']) . '? They are logged out at once."' : '' ?>>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $a['id']) ?>">
                                    <input type="hidden" name="action" value="<?= $active ? 'deactivate' : 'activate' ?>">
                                    <button type="submit" class="btn btn--<?= $active ? 'danger-ghost' : 'ghost' ?> btn--small"><?= $active ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                            <?php else: ?><span class="muted">—</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-panel">
        <header class="admin-panel__header"><h2 class="admin-panel__title"><?= $editing !== null ? 'Edit ' . e($editing['full_name']) : 'Add a staff member' ?></h2></header>
        <?php if ($errors !== []): ?><div class="alert alert--error panel-alert"><span>Please correct the highlighted fields.</span></div><?php endif; ?>
        <form method="post" action="<?= e(url('admin/staff.php', $editing !== null ? ['edit' => $editing['id']] : [])) ?>" class="admin-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editing !== null ? 'update' : 'create' ?>">
            <?php if ($editing !== null): ?><input type="hidden" name="id" value="<?= e((string) $editing['id']) ?>"><?php endif; ?>
            <div class="field">
                <label for="full_name">Name</label>
                <input type="text" id="full_name" name="full_name" value="<?= e((string) ($old['full_name'] ?? '')) ?>" maxlength="100" required<?= field_attrs($errors, 'full_name') ?>>
                <?= field_error($errors, 'full_name') ?>
            </div>
            <?php if ($editing === null): ?>
                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= e((string) ($old['username'] ?? '')) ?>" maxlength="30" autocomplete="off" placeholder="e.g. nimal.staff" required<?= field_attrs($errors, 'username') ?>>
                    <?= field_error($errors, 'username') ?>
                    <p class="hint">Lower-case letters, numbers, dot, dash or underscore. Used to log in; cannot be changed later.</p>
                </div>
            <?php else: ?>
                <p class="muted">Username: <strong><?= e($editing['username']) ?></strong></p>
            <?php endif; ?>
            <div class="field">
                <label for="email">E-mail <span class="optional">(optional)</span></label>
                <input type="email" id="email" name="email" value="<?= e((string) ($old['email'] ?? '')) ?>" maxlength="190"<?= field_attrs($errors, 'email') ?>>
                <?= field_error($errors, 'email') ?>
            </div>
            <fieldset class="permission-box<?= isset($errors['permissions']) ? ' has-error' : '' ?>">
                <legend>Can work in</legend>
                <?php foreach (StaffService::PERMISSIONS as $key => [$label, $help]): ?>
                    <label class="check permission-box__item"><input type="checkbox" name="permissions[]" value="<?= e($key) ?>"<?= $checked($key) ? ' checked' : '' ?>>
                        <span><strong><?= e($label) ?></strong><br><small class="muted"><?= e($help) ?></small></span></label>
                <?php endforeach; ?>
                <?= field_error($errors, 'permissions') ?>
            </fieldset>
            <?php if ($editing === null): ?>
                <p class="hint">A strong password is generated when you press the button and shown to you once.</p>
            <?php endif; ?>
            <button type="submit" class="btn btn--primary"><?= $editing !== null ? 'Save changes' : 'Create staff account and generate password' ?></button>
            <?php if ($editing !== null): ?><a href="<?= e(url('admin/staff.php')) ?>">Cancel</a><?php endif; ?>
        </form>
    </section>
</div>
