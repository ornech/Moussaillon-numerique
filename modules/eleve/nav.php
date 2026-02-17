    <header class="status-bar">
        <div class="ship-info">
            <img src="../../assets/img/ships/<?php echo $user['img_url']; ?>" class="ship-img">
            <div>
                <strong><?php echo htmlspecialchars($user['ship_name']); ?></strong><br>
                <small>Portée :<?php echo isset($user['range_level']) ? $user['range_level'] : 1; ?> milles</small>
               
            </div>
        </div>
        <div style="font-size: 1.5rem;">🪙 <strong><?php echo $user['points']; ?></strong></div>
        <a href="parcours.php" class="btn-port">Naviguer</a>
        <a href="port.php" class="btn-port">⚓ Rentrer au port</a>
        <a href="../../api/logout.php" class="btn-port" style="background:var(--danger); box-shadow: 0 4px 0 #991b1b;">Déconnexion</a>

    </header>
