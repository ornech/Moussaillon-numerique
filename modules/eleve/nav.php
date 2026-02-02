    <header class="status-bar">
        <div class="ship-info">
            <img src="../../assets/img/ships/<?php echo $user['img_url']; ?>" class="ship-img">
            <div>
                <strong><?php echo htmlspecialchars($user['ship_name']); ?></strong><br>
                <small>Portée : <?php echo $user['range_level']; ?> milles</small>
            </div>
        </div>
        <div style="font-size: 1.5rem;">🪙 <strong><?php echo $user['points']; ?></strong></div>
        <a href="parcours.php" class="btn-port">Naviguer</a>
        <a href="port.php" class="btn-port">⚓ Rentrer au port</a>
    </header>
