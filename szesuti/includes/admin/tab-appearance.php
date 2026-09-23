<?php
/**
 * Admin – Megjelenés fül.
 *
 * @var array         $s    Beállítások.
 * @var SZESUTI_Admin $this Admin osztály.
 *
 * @package SZESUTI
 */

defined( 'ABSPATH' ) || exit;

$a = $s['appearance'];

SZESUTI_F::section(
	__( 'Elrendezés', 'szesuti' ),
	__( 'A banner alakja és helye. Mobilon minden elrendezés automatikusan teljes szélességű lesz.', 'szesuti' )
);

SZESUTI_F::table_start();

SZESUTI_F::select(
	array( 'appearance', 'layout' ),
	$a['layout'],
	array(
		'box'   => __( 'Lebegő doboz', 'szesuti' ),
		'bar'   => __( 'Teljes szélességű sáv', 'szesuti' ),
		'modal' => __( 'Középre igazított ablak', 'szesuti' ),
	),
	array(
		'label' => __( 'Elrendezés', 'szesuti' ),
		'desc'  => __( 'A középre igazított ablak elfogadási aránya a legmagasabb, de a legtolakodóbb is. A sáv a legkevésbé zavaró.', 'szesuti' ),
	)
);

SZESUTI_F::select(
	array( 'appearance', 'position' ),
	$a['position'],
	array(
		'bottom-left'   => __( 'Bal alul', 'szesuti' ),
		'bottom-right'  => __( 'Jobb alul', 'szesuti' ),
		'bottom-center' => __( 'Középen alul', 'szesuti' ),
		'top-left'      => __( 'Bal felül', 'szesuti' ),
		'top-right'     => __( 'Jobb felül', 'szesuti' ),
		'top-center'    => __( 'Középen felül', 'szesuti' ),
	),
	array(
		'label' => __( 'Pozíció', 'szesuti' ),
		'desc'  => __( 'Sáv elrendezésnél csak a felül/alul rész számít, középre igazított ablaknál nincs hatása.', 'szesuti' ),
	)
);

SZESUTI_F::range(
	array( 'appearance', 'width' ),
	$a['width'],
	array(
		'label' => __( 'Szélesség', 'szesuti' ),
		'min'   => 260,
		'max'   => 900,
		'step'  => 10,
	)
);

SZESUTI_F::range(
	array( 'appearance', 'radius' ),
	$a['radius'],
	array(
		'label' => __( 'Sarok lekerekítése', 'szesuti' ),
		'min'   => 0,
		'max'   => 32,
	)
);

SZESUTI_F::select(
	array( 'appearance', 'shadow' ),
	$a['shadow'],
	array(
		'none' => __( 'Nincs', 'szesuti' ),
		'sm'   => __( 'Finom', 'szesuti' ),
		'md'   => __( 'Közepes', 'szesuti' ),
		'lg'   => __( 'Erős', 'szesuti' ),
	),
	array( 'label' => __( 'Árnyék', 'szesuti' ) )
);

SZESUTI_F::select(
	array( 'appearance', 'animation' ),
	$a['animation'],
	array(
		'none'       => __( 'Nincs', 'szesuti' ),
		'fade'       => __( 'Halványítás', 'szesuti' ),
		'slide-up'   => __( 'Felúszás', 'szesuti' ),
		'slide-side' => __( 'Beúszás oldalról', 'szesuti' ),
		'zoom'       => __( 'Nagyítás', 'szesuti' ),
	),
	array( 'label' => __( 'Animáció', 'szesuti' ) )
);

SZESUTI_F::checkbox(
	array( 'appearance', 'overlay' ),
	$a['overlay'],
	array(
		'label' => __( 'Háttér sötétítése', 'szesuti' ),
		'text'  => __( 'Sötét réteg a banner mögött', 'szesuti' ),
		'desc'  => __( 'Középre igazított ablaknál mindig bekapcsolt. A sötétítés növeli az elfogadási arányt, de akadályozza az oldal használatát döntés előtt.', 'szesuti' ),
	)
);

SZESUTI_F::range(
	array( 'appearance', 'overlay_opacity' ),
	$a['overlay_opacity'],
	array(
		'label' => __( 'Sötétítés erőssége', 'szesuti' ),
		'min'   => 0,
		'max'   => 95,
		'unit'  => '%',
	)
);

SZESUTI_F::text(
	array( 'appearance', 'z_index' ),
	$a['z_index'],
	array(
		'label' => __( 'z-index', 'szesuti' ),
		'type'  => 'number',
		'class' => 'regular-text',
		'min'   => 10000,
		'max'   => 2147483000,
		'desc'  => __( 'Csak akkor módosítsd, ha valami (pl. egy sticky menü) a banner fölé kerül. A legkisebb elfogadott érték 10000, hogy a banner ne csússzon a téma elemei alá.', 'szesuti' ),
	)
);

SZESUTI_F::table_end();

/* ------------------------------------------------------------------ */

SZESUTI_F::section( __( 'Színek', 'szesuti' ) );

SZESUTI_F::table_start();
SZESUTI_F::select(
	array( 'appearance', 'theme' ),
	$a['theme'],
	array(
		'light' => __( 'Mindig világos', 'szesuti' ),
		'dark'  => __( 'Mindig sötét', 'szesuti' ),
		'auto'  => __( 'A látogató rendszerbeállítása szerint', 'szesuti' ),
	),
	array(
		'label' => __( 'Színséma', 'szesuti' ),
		'desc'  => __( 'Az automatikus módban a böngésző sötét témájánál a lenti sötét színek lépnek életbe.', 'szesuti' ),
	)
);
SZESUTI_F::table_end();

$szesuti_color_labels = array(
	'bg'             => __( 'Háttér', 'szesuti' ),
	'text'           => __( 'Szöveg', 'szesuti' ),
	'muted'          => __( 'Halvány szöveg', 'szesuti' ),
	'border'         => __( 'Keret', 'szesuti' ),
	'primary'         => __( 'Elfogadás gomb háttere', 'szesuti' ),
	'primary_text'    => __( 'Elfogadás gomb szövege', 'szesuti' ),
	'secondary'       => __( 'Elutasítás gomb háttere', 'szesuti' ),
	'secondary_text'  => __( 'Elutasítás gomb szövege', 'szesuti' ),
	'tertiary'        => __( 'Beállítások gomb háttere', 'szesuti' ),
	'tertiary_text'   => __( 'Beállítások gomb szövege', 'szesuti' ),
	'tertiary_border' => __( 'Beállítások gomb kerete', 'szesuti' ),
	'link'           => __( 'Link', 'szesuti' ),
	'toggle_on'      => __( 'Bekapcsolt kapcsoló', 'szesuti' ),
	'overlay'        => __( 'Sötétítő réteg', 'szesuti' ),
);

// Kontrasztőr visszajelzése: ha egy színpár olvashatatlan lenne, a banner
// automatikusan olvasható színt használ helyette – ezt itt jelezzük.
$szesuti_contrast_groups = array(
	__( 'Világos téma', 'szesuti' ) => SZESUTI_Colors::safe_palette( (array) $a['colors'] ),
);
if ( 'light' !== $a['theme'] ) {
	$szesuti_contrast_groups[ __( 'Sötét téma', 'szesuti' ) ] = SZESUTI_Colors::safe_palette( (array) $a['colors_dark'] );
}
$szesuti_contrast_msgs = array();
foreach ( $szesuti_contrast_groups as $szesuti_group_name => $szesuti_palette ) {
	if ( ! empty( $szesuti_palette['fixes'] ) ) {
		$szesuti_contrast_msgs[] = '<strong>' . esc_html( $szesuti_group_name ) . ':</strong> ' . esc_html( implode( ', ', $szesuti_palette['fixes'] ) );
	}
}
if ( ! empty( $szesuti_contrast_msgs ) ) {
	SZESUTI_F::notice(
		__( 'Néhány színpár túl kevés kontrasztot adna, ezért a banner ezeknél <strong>automatikusan olvasható színt használ</strong> (a gombok így mindig láthatók és használhatók maradnak). Érdemes a színeket úgy módosítani, hogy ne legyen szükség javításra:', 'szesuti' )
		. '<br>' . implode( '<br>', $szesuti_contrast_msgs ),
		'warning'
	);
}

echo '<p class="description szesuti-colors-note">' . esc_html__( 'A Beállítások gomb színei üresen hagyhatók („automatikus”): ilyenkor átlátszó hátterű, halvány feliratú, vékony keretes gomb lesz. Az × gomb visszaállítja az automatikusra.', 'szesuti' ) . '</p>';

echo '<div class="szesuti-colors-grid">';

$szesuti_optional_colors = array(
	'tertiary'        => 'bg',
	'tertiary_text'   => 'muted',
	'tertiary_border' => 'border',
);

foreach ( array( 'colors' => __( 'Világos téma', 'szesuti' ), 'colors_dark' => __( 'Sötét téma', 'szesuti' ) ) as $szesuti_set => $szesuti_set_label ) {
	echo '<div class="szesuti-colors-col"><h3>' . esc_html( $szesuti_set_label ) . '</h3>';
	foreach ( $szesuti_color_labels as $szesuti_key => $szesuti_label ) {
		$szesuti_value    = isset( $a[ $szesuti_set ][ $szesuti_key ] ) ? $a[ $szesuti_set ][ $szesuti_key ] : '';
		$szesuti_optional = isset( $szesuti_optional_colors[ $szesuti_key ] );
		$szesuti_fallback = $szesuti_optional && ! empty( $a[ $szesuti_set ][ $szesuti_optional_colors[ $szesuti_key ] ] )
			? $a[ $szesuti_set ][ $szesuti_optional_colors[ $szesuti_key ] ]
			: '#ffffff';
		SZESUTI_F::color(
			array( 'appearance', $szesuti_set, $szesuti_key ),
			$szesuti_value,
			array(
				'label'    => $szesuti_label,
				'optional' => $szesuti_optional,
				'fallback' => $szesuti_fallback,
			)
		);
	}
	echo '</div>';
}

echo '</div>';

/* ------------------------------------------------------------------ */

SZESUTI_F::section( __( 'Gombok', 'szesuti' ) );
SZESUTI_F::table_start();

SZESUTI_F::checkbox(
	array( 'appearance', 'show_reject' ),
	$a['show_reject'],
	array(
		'important' => true,
		'label' => __( '„Elutasítás” gomb', 'szesuti' ),
		'text'  => __( 'Megjelenjen a banneren', 'szesuti' ),
		'desc'  => __( '<strong>Erősen ajánlott bekapcsolva hagyni.</strong> Az elutasításnak ugyanolyan könnyen elérhetőnek kell lennie, mint az elfogadásnak – a NAIH és az európai adatvédelmi hatóságok ezt következetesen elvárják.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'appearance', 'show_settings' ),
	$a['show_settings'],
	array(
		'label' => __( '„Beállítások” gomb', 'szesuti' ),
		'text'  => __( 'Megjelenjen a banneren', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'appearance', 'show_close_x' ),
	$a['show_close_x'],
	array(
		'label' => __( 'Bezárás (X) gomb', 'szesuti' ),
		'text'  => __( 'Jelenjen meg a sarokban', 'szesuti' ),
		'desc'  => __( 'Az X gomb elutasításként viselkedik (csak a szükséges sütik maradnak). Ne használd „bezárom és mehet tovább minden” jelentéssel, mert az nem érvényes hozzájárulás.', 'szesuti' ),
	)
);

SZESUTI_F::text(
	array( 'appearance', 'btn_order' ),
	$a['btn_order'],
	array(
		'label' => __( 'Gombok sorrendje', 'szesuti' ),
		'desc'  => __( 'Vesszővel elválasztva: <code>accept</code>, <code>reject</code>, <code>settings</code>. Például: <code>reject,settings,accept</code>', 'szesuti' ),
	)
);

SZESUTI_F::select(
	array( 'appearance', 'btn_style' ),
	$a['btn_style'],
	array(
		'solid'   => __( 'Kitöltött', 'szesuti' ),
		'outline' => __( 'Körvonalas', 'szesuti' ),
		'soft'    => __( 'Lágy (halvány háttér)', 'szesuti' ),
	),
	array( 'label' => __( 'Gombstílus', 'szesuti' ) )
);

SZESUTI_F::select(
	array( 'appearance', 'btn_layout' ),
	$a['btn_layout'],
	array(
		'row'    => __( 'Egymás mellett', 'szesuti' ),
		'column' => __( 'Egymás alatt', 'szesuti' ),
	),
	array( 'label' => __( 'Gombok elrendezése', 'szesuti' ) )
);

SZESUTI_F::checkbox(
	array( 'appearance', 'equal_buttons' ),
	$a['equal_buttons'],
	array(
		'label' => __( 'Azonos gombszélesség', 'szesuti' ),
		'text'  => __( 'A gombok töltsék ki egyenlően a sort', 'szesuti' ),
		'desc'  => __( 'Ez is az „egyenrangú választás” elvét támogatja: az elfogadás ne legyen feltűnőbb az elutasításnál.', 'szesuti' ),
	)
);

SZESUTI_F::range(
	array( 'appearance', 'btn_radius' ),
	$a['btn_radius'],
	array(
		'label' => __( 'Gomb lekerekítése', 'szesuti' ),
		'min'   => 0,
		'max'   => 30,
	)
);

SZESUTI_F::table_end();

/* ------------------------------------------------------------------ */

SZESUTI_F::section( __( 'Ikon és tipográfia', 'szesuti' ) );
SZESUTI_F::table_start();

SZESUTI_F::icon_picker(
	array( 'appearance', 'icon' ),
	$a['icon'],
	array( 'appearance', 'logo_url' ),
	$a['logo_url'],
	array(
		'label' => __( 'Ikon a banneren', 'szesuti' ),
		'desc'  => __( 'A „Saját kép”-re kattintva a médiatárból választhatsz, vagy a megnyíló ablak „Fájlok feltöltése” fülén a gépedről tölthetsz fel képet. Négyzetes, átlátszó hátterű PNG vagy SVG mutat a legjobban.', 'szesuti' ),
	)
);

SZESUTI_F::range(
	array( 'appearance', 'logo_height' ),
	$a['logo_height'],
	array(
		'label' => __( 'Képes ikon mérete', 'szesuti' ),
		'min'   => 20,
		'max'   => 80,
		'desc'  => __( 'Csak a pixeles sütinél és a saját képnél számít; a rajzolt ikonok mérete fix.', 'szesuti' ),
	)
);

SZESUTI_F::range(
	array( 'appearance', 'font_size' ),
	$a['font_size'],
	array(
		'label' => __( 'Alap betűméret', 'szesuti' ),
		'min'   => 11,
		'max'   => 20,
	)
);

SZESUTI_F::text(
	array( 'appearance', 'font_family' ),
	$a['font_family'],
	array(
		'label'       => __( 'Betűtípus', 'szesuti' ),
		'class'       => 'large-text',
		'placeholder' => 'Inter, Arial, sans-serif',
		'desc'        => __( 'Hagyd üresen a rendszer-betűtípushoz. A plugin nem tölt be külső betűtípust – csak olyat adj meg, ami a témádban már elérhető.', 'szesuti' ),
	)
);

SZESUTI_F::checkbox(
	array( 'appearance', 'show_powered_by' ),
	$a['show_powered_by'],
	array(
		'label' => __( '„SZetemény” felirat', 'szesuti' ),
		'text'  => __( 'Megjelenjen a banner alján', 'szesuti' ),
	)
);

SZESUTI_F::table_end();

/* ------------------------------------------------------------------ */

SZESUTI_F::section(
	__( 'Egyedi CSS', 'szesuti' ),
	__( 'A banner minden eleme <code>szesuti-</code> előtagú osztályt használ. Mivel a plugin stílusa a téma felülírásai ellen védve van, a saját szabályokat a <code>#szesuti-root</code> elé kell írni.', 'szesuti' )
);
SZESUTI_F::table_start();

SZESUTI_F::textarea(
	array( 'appearance', 'custom_css' ),
	$a['custom_css'],
	array(
		'label'       => __( 'CSS', 'szesuti' ),
		'rows'        => 10,
		'code'        => true,
		'placeholder' => "#szesuti-root .szesuti-banner { }\n#szesuti-root .szesuti-btn--primary { }",
		'desc'        => __( 'A szabályok a plugin saját stílusa után töltődnek be, így felülírják azt.', 'szesuti' ),
	)
);

SZESUTI_F::table_end();
