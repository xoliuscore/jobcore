<?php
/**
 * Compact Jobs menu in the WordPress sidebar: the same groups as the JobCore panel
 * (Overview, Content, Board, System, Add-ons), each folding open and shut.
 *
 * WordPress prints the menu first; right after it (the "adminmenu" action) a small script
 * swaps the long list of Jobs sub-items for the grouped one, so nothing jumps on screen.
 * Every page stays reachable at its own address — only the list in the sidebar changes.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

const WPJC_MENU_PARENT = 'edit.php?post_type=wpjc_job';

/**
 * Comparable key of an admin address: file plus the parameters that pick the screen.
 *
 * @param string $url Admin URL or menu slug.
 * @return array{0:string,1:array<string,string>} [file, params].
 */
function wpjc_menu_key( $url ) {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$file = basename( '' !== $path ? $path : 'edit.php' );
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
	$keep = array();
	foreach ( array( 'post_type', 'taxonomy', 'page', 'tab' ) as $k ) {
		if ( isset( $q[ $k ] ) && is_scalar( $q[ $k ] ) ) {
			$keep[ $k ] = (string) $q[ $k ];
		}
	}
	if ( 'edit-tags.php' === $file ) {
		unset( $keep['post_type'] ); // A taxonomy screen is the same with or without it.
	}
	return array( $file, $keep );
}

/**
 * Admin address of a registered Jobs sub-item.
 *
 * @param string $slug Submenu slug.
 * @return string
 */
function wpjc_menu_item_url( $slug ) {
	if ( false !== strpos( $slug, '.php' ) || false !== strpos( $slug, '://' ) ) {
		return false !== strpos( $slug, '://' ) ? $slug : admin_url( $slug );
	}
	return admin_url( WPJC_MENU_PARENT . '&page=' . $slug );
}

/**
 * How well an item matches the screen on view (0 = not at all).
 *
 * @param array $item [file, params] of the item.
 * @param array $here [file, params] of the screen.
 * @return int
 */
function wpjc_menu_score( array $item, array $here ) {
	if ( $item[0] !== $here[0] ) {
		return 0;
	}
	foreach ( $item[1] as $k => $v ) {
		if ( ( $here[1][ $k ] ?? null ) !== $v ) {
			// A settings page without a tab is its first tab.
			if ( 'tab' === $k && ! isset( $here[1]['tab'] ) && 'look' === $v ) {
				continue;
			}
			return 0;
		}
	}
	return 1 + count( $item[1] );
}

/**
 * The grouped menu: [ [label, [ [label HTML, url, current] … ] ] … ].
 *
 * @return array
 */
function wpjc_menu_groups() {
	global $submenu;
	$registered = array();
	foreach ( (array) ( $submenu[ WPJC_MENU_PARENT ] ?? array() ) as $row ) {
		if ( empty( $row[2] ) || ! current_user_can( $row[1] ) ) {
			continue;
		}
		$url          = wpjc_menu_item_url( (string) $row[2] );
		$registered[] = array( (string) $row[0], $url, wpjc_menu_key( $url ) );
	}

	// The screen on view, with edit / term screens counted as their list.
	$here   = wpjc_menu_key( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && in_array( $here[0], array( 'post.php', 'post-new.php' ), true ) && $screen->post_type ) {
		$here = 'post-new.php' === $here[0] && 'wpjc_job' === $screen->post_type ? $here : array( 'edit.php', array( 'post_type' => $screen->post_type ) );
	} elseif ( $screen && 'term.php' === $here[0] && $screen->taxonomy ) {
		$here = array( 'edit-tags.php', array( 'taxonomy' => $screen->taxonomy ) );
	}

	$groups = array();
	$used   = array();
	foreach ( wpjc_admin_nav() as $group => $items ) {
		$rows = array();
		foreach ( $items as $item ) {
			$url  = (string) $item[3];
			$key  = wpjc_menu_key( $url );
			$text = esc_html( $item[0] );
			// Keep the counter bubble WordPress shows on the registered item.
			foreach ( $registered as $i => $reg ) {
				if ( $reg[2] === $key || ( $reg[2][0] === $key[0] && array_diff_key( $key[1], array( 'tab' => 1 ) ) === $reg[2][1] ) ) {
					if ( $reg[2] === $key && false !== strpos( $reg[0], '<span' ) ) {
						$text = esc_html( $item[0] ) . ' ' . wp_kses( preg_replace( '/^[^<]*/', '', $reg[0] ), array( 'span' => array( 'class' => true ) ) );
					}
					$used[ $i ] = true;
				}
			}
			$rows[] = array( $text, $url, $key );
		}
		$groups[ $group ] = $rows;
	}

	// Items an add-on registered without a place in the panel: lists go to Content, the rest to Add-ons.
	$content = array_keys( $groups )[2] ?? __( 'Content', 'jobcore' );
	foreach ( $registered as $i => $reg ) {
		if ( isset( $used[ $i ] ) ) {
			continue;
		}
		$to                = 'edit.php' === $reg[2][0] && isset( $reg[2][1]['post_type'] ) && ! isset( $reg[2][1]['page'] ) ? __( 'Content', 'jobcore' ) : __( 'Add-ons', 'wp-job-core' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- shared group label of JobCore add-ons.
		$to                = isset( $groups[ $to ] ) || __( 'Add-ons', 'wp-job-core' ) === $to ? $to : $content; // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- shared group label.
		$groups[ $to ][]   = array( wp_kses( $reg[0], array( 'span' => array( 'class' => true ) ) ), $reg[1], $reg[2] );
	}

	// Mark the best match as current.
	$best = array( 0, '', 0 );
	foreach ( $groups as $group => $rows ) {
		foreach ( $rows as $n => $row ) {
			$score = wpjc_menu_score( $row[2], $here );
			if ( $score > $best[0] ) {
				$best = array( $score, $group, $n );
			}
		}
	}
	$out = array();
	foreach ( $groups as $group => $rows ) {
		$list = array();
		foreach ( $rows as $n => $row ) {
			$list[] = array( $row[0], $row[1], $best[0] && $best[1] === $group && $best[2] === $n );
		}
		$out[] = array( (string) $group, $list );
	}
	return $out;
}

/** Swap the Jobs sub-items for the grouped menu, right after WordPress printed the sidebar. */
function wpjc_print_compact_menu() {
	if ( ! current_user_can( 'edit_posts' ) || ! (bool) apply_filters( 'wpjc_compact_admin_menu', true ) ) {
		return;
	}
	$groups = wpjc_menu_groups();
	if ( ! $groups ) {
		return;
	}
	?>
	<style>
		#adminmenu .wpjc-mg{position:relative;margin:0;padding:0}
		#adminmenu .wpjc-mg > a{display:flex !important;align-items:center;gap:6px}
		#adminmenu .wpjc-mg > a::after{content:"";width:6px;height:6px;margin-left:auto;border:solid currentColor;border-width:1.5px 1.5px 0 0;transform:rotate(45deg);opacity:.6}
		#adminmenu .wpjc-mg.is-current > a{color:#fff;font-weight:600}
		#adminmenu .wpjc-fly{position:absolute;top:-7px;left:100%;z-index:9999;display:none;min-width:190px;margin:0;padding:7px 0 8px;box-shadow:0 3px 5px rgba(0,0,0,.2)}
		#adminmenu .wpjc-mg:hover > .wpjc-fly,#adminmenu .wpjc-mg:focus-within > .wpjc-fly,#adminmenu .wpjc-mg.is-open > .wpjc-fly{display:block}
		#adminmenu .wpjc-mg:hover > a,#adminmenu .wpjc-mg:focus-within > a{color:var(--wpjc-menu-hover,#72aee6)}
		#adminmenu .wpjc-fly li{margin:0}
		#adminmenu .wpjc-fly a{display:block;padding:5px 12px;line-height:1.4;white-space:nowrap}
		#adminmenu .wpjc-fly .current a{color:#fff;font-weight:600}
		#adminmenu .wpjc-fly .wpjc-fly__head{padding:3px 12px 6px;color:rgba(240,246,252,.55);font-size:10.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase}
		#adminmenu .wpjc-fly .awaiting-mod,#adminmenu .wpjc-fly .update-plugins{margin-left:4px}
		@media (max-width:782px){
			#adminmenu .wpjc-fly{position:static;display:none;box-shadow:none;padding:0 0 4px 12px;background:none !important}
			#adminmenu .wpjc-mg.is-open > .wpjc-fly{display:block}
			#adminmenu .wpjc-mg > a::after{transform:rotate(135deg)}
			#adminmenu .wpjc-fly__head{display:none}
		}
	</style>
	<script>
	(function () {
		var li = document.getElementById('menu-posts-wpjc_job');
		var ul = li && li.querySelector('.wp-submenu');
		if (!ul) { return; }
		var groups = <?php echo wp_json_encode( $groups ); ?>;
		var head = ul.querySelector('.wp-submenu-head');
		var bg = getComputedStyle(ul).backgroundColor;
		if (!bg || bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent') { bg = '#2c3338'; }
		ul.innerHTML = '';
		if (head) { ul.appendChild(head); }
		var phone = window.matchMedia('(max-width: 782px)');
		groups.forEach(function (g, gi) {
			var current = g[1].some(function (r) { return r[2]; });
			// "Overview" with only the dashboard is a plain item.
			if (gi === 0 && g[1].length === 1) {
				var one = document.createElement('li');
				one.className = current ? 'current' : '';
				one.innerHTML = '<a></a>';
				one.firstChild.href = g[1][0][1];
				one.firstChild.innerHTML = g[1][0][0];
				if (current) { one.firstChild.className = 'current'; one.firstChild.setAttribute('aria-current', 'page'); }
				ul.appendChild(one);
				return;
			}
			var h = document.createElement('li');
			h.className = 'wpjc-mg' + (current ? ' is-current' : '');
			var a = document.createElement('a');
			a.href = g[1][0] ? g[1][0][1] : '#';
			a.textContent = g[0];
			a.setAttribute('aria-haspopup', 'true');
			h.appendChild(a);
			var fly = document.createElement('ul');
			fly.className = 'wpjc-fly';
			fly.style.background = bg;
			var t = document.createElement('li');
			t.className = 'wpjc-fly__head';
			t.setAttribute('aria-hidden', 'true');
			t.textContent = g[0];
			fly.appendChild(t);
			g[1].forEach(function (r) {
				var i = document.createElement('li');
				if (r[2]) { i.className = 'current'; }
				var x = document.createElement('a');
				x.href = r[1];
				x.innerHTML = r[0];
				if (r[2]) { x.setAttribute('aria-current', 'page'); }
				i.appendChild(x);
				fly.appendChild(i);
			});
			h.appendChild(fly);
			ul.appendChild(h);
			// Phones have no hover: the first tap opens the group, the second follows the link.
			a.addEventListener('click', function (ev) {
				if (phone.matches && !h.classList.contains('is-open')) {
					ev.preventDefault();
					h.classList.add('is-open');
				}
			});
		});
	})();
	</script>
	<?php
}
add_action( 'adminmenu', 'wpjc_print_compact_menu' );
