<?php
// Simulación de menú basado en permisos (debes reemplazar con tu lógica real)
$menuItems = [
    'dashboard' => [
        'icon' => 'fas fa-tachometer-alt',
        'title' => 'Dashboard',
        'url' => '/dashboard.php',
        'active' => basename($_SERVER['PHP_SELF']) === 'dashboard.php'
    ],
    'modulos' => [
        'icon' => 'fas fa-cubes',
        'title' => 'Módulos',
        'url' => '/pages/empresas.php',
        'active' => basename($_SERVER['PHP_SELF']) === 'empresas.php',
        'subitems' => [
            [
                'title' => 'Listado',
                'url' => '/pages/empresas.php',
                'active' => basename($_SERVER['PHP_SELF']) === 'empresas.php'
            ],
            [
                'title' => 'Reportes',
                'url' => '#',
                'active' => false
            ]
        ]
    ]
    // Puedes agregar más items según los permisos del usuario
];

function renderMenuItems($items) {
    echo '<ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">';
    
    foreach ($items as $item) {
        $hasChildren = !empty($item['subitems']);
        $isActive = $item['active'] ?? false;
        
        echo '<li class="nav-item' . ($hasChildren ? ' has-treeview' : '') . ($isActive ? ' menu-open' : '') . '">';
        echo '<a href="' . $item['url'] . '" class="nav-link' . ($isActive ? ' active' : '') . '">';
        echo '<i class="nav-icon ' . $item['icon'] . '"></i>';
        echo '<p>' . $item['title'];
        if ($hasChildren) {
            echo '<i class="right fas fa-angle-left"></i>';
        }
        echo '</p></a>';
        
        if ($hasChildren) {
            echo '<ul class="nav nav-treeview">';
            foreach ($item['subitems'] as $subitem) {
                $subActive = $subitem['active'] ?? false;
                echo '<li class="nav-item">';
                echo '<a href="' . $subitem['url'] . '" class="nav-link' . ($subActive ? ' active' : '') . '">';
                echo '<i class="far fa-circle nav-icon"></i>';
                echo '<p>' . $subitem['title'] . '</p>';
                echo '</a></li>';
            }
            echo '</ul>';
        }
        
        echo '</li>';
    }
    
    echo '</ul>';
}

// Renderizar el menú
renderMenuItems($menuItems);
?>