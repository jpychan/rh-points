<?php

require_once plugin_dir_path( __FILE__ ) . 'class-rogershood-points-list-table.php';

class Rogershood_Points_Admin {
	private $user_points_list_table;

	public function __construct() {
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'set-screen-option', array( $this, 'save_screen_options' ), 10, 3 );
		add_action( 'admin_post_adjust_points', array( $this, 'handle_point_adjustment' ) );
		add_filter( 'admin_title', array( $this, 'edit_page_title' ), 10, 2 );
		add_action( 'admin_post_save_points_settings', array( $this, 'handle_points_settings_form' ) );

	}

	public function edit_page_title( $admin_title, $title ) {
		if ( get_current_screen()->id === 'admin_page_rogershood_user_points_edit' ) {
			$admin_title = 'Edit Points for User ' . $admin_title;
		}

		return $admin_title;
	}


	public function add_admin_menu() {
		$hook = add_menu_page(
			'Rogershood Points',
			'User Points',
			'manage_options',
			'rogershood-points',
			array( $this, 'admin_page' ),
			'dashicons-carrot',
			6
		);

		// Hidden submenu for editing existing reviews
		add_submenu_page(
			null,
			'Edit User Points',
			'Edit User Points',
			'manage_options',
			'rogershood_user_points_edit',
			array( $this, 'render_user_points_edit_page' ),
		);

		add_action( "load-$hook", array( $this, 'screen_option' ) );

		add_submenu_page(
			'rogershood-points',
			'Settings',
			'Settings',
			'manage_options',
			'rogershood_points_settings',
			array( $this, 'render_points_settings_page' ),
		);

	}

	public function screen_option() {
		$option = 'per_page';
		$args   = array(
			'label'   => 'Users',
			'default' => 10,
			'option'  => 'user_points_per_page'
		);
		add_screen_option( $option, $args );

		$this->user_points_list_table = new Rogershood_Points_List_Table();
	}

	public function save_screen_options( $status, $option, $value ) {
		if ( $option === 'user_points_per_page' ) {
			return $value;
		}

		return $status;
	}

	public function set_screen( $status, $option, $value ) {
		return $value;
	}

	public function admin_page() {

		$this->user_points_list_table->prepare_items();
		$page = $_REQUEST['page'] ?? 1;
		$orderby = $_GET['orderby'] ?? '';
		$order = $_GET['order'] ?? '';

		?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Rogershood Points</h1>
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>" />
                <input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>" />
                <input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>" />
                <?php
				$this->user_points_list_table->views();
				$this->user_points_list_table->search_box( 'Search', 'search_id' );
				$this->user_points_list_table->display();
				?>
            </form>
        </div>
		<?php
	}

	public function render_user_points_edit_page() {

		$user_id = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : null;

		if ( ! $user_id ) {
			wp_die( 'User ID is required' );
		}

		$points = new Rogershood_Points( $user_id );
		$user   = get_userdata( $user_id );

		$arr = array(
			'user_id' => $user_id,
		);

		$transactions   = Rogershood_Points_Transaction::find_by( $arr, - 1, 0, 'transaction_date', 'DESC' );
		$admin_post_url = admin_url( 'admin-post.php' );
		// generate nonce
		$nonce = wp_create_nonce( 'rogershood_adjust_points_nonce' );

		?>
        <div class="user-point-edit-wrap">
            <h1>Points for <?php echo $user->display_name ?></h1>
			<?php if ( isset( $_GET['points_updated'] ) && ( $_GET['points_updated'] ) === '1' ): ?>
                <div id="message" class="updated notice is-dismissible">
                    <p>Points adjusted successfully.</p>
                </div>
			<?php endif; ?>
            <div style="display:grid; grid-template-columns: repeat(3, 200px);">
                <div>
                    <h3>Current Points</h3>
                    <p><?php echo $points->get_current_points(); ?>
                        <button class="button"
                                onclick="document.getElementById('adjust-points').style.display = 'block'; return false;">
                            Edit
                        </button>

                    </p>
                </div>
                <div>
                    <h3>Redeemed Points</h3>
                    <p><?php echo $points->get_redeemed_points(); ?></p>
                </div>
                <div>
                    <h3>Total Points</h3>
                    <p><?= esc_html( $points->get_total_points() ) ?></p>
                </div>
            </div>
            <form action="<?= esc_url( $admin_post_url ) ?>" method="post" id="adjust-points" style="display:none;">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="action" value="adjust_points">
                <input type="hidden" name="nonce" value="<?= esc_attr( $nonce ); ?>">
                <input type="number" value="<?= esc_attr( $points->get_current_points() ) ?>" name="points">
                <input type="submit" value="Adjust Points" class="button">
                <button class="button"
                        onclick="document.getElementById('adjust-points').style.display = 'none'; return false;">Cancel
                </button>
            </form>

            <h2>Points Log</h2>
            <div>
                <div style="display:grid; grid-template-columns: 200px 100px 400px; font-weight: bold;">
                    <p>Date</p>
                    <p>Points</p>
                    <p>Action</p>
                </div>

				<?php
				foreach ( $transactions as $transaction ) {
					$action = $transaction->get_transaction_action_label();

                    if (!empty($transaction->get_order_id())) {
                        $order_admin_url = admin_url('admin.php?page=wc-orders&action=edit&id=' . $transaction->get_order_id() );
                        $action .= ' (Order <a href="' . esc_url( $order_admin_url ) . '">#' . esc_html( $transaction->get_order_id() ) . '</a>)';
//                        $action .= ' (Order #' . $transaction->get_order_id() . ')';
                    }
					$points = $transaction->get_transaction_type() === 'debit' ? '-' . $transaction->get_points() : $transaction->get_points();
					?>
                    <div style="display:grid; grid-template-columns: 200px 100px 400px">
                        <p><?php echo $transaction->get_transaction_date(); ?></p>
                        <p><?php echo $points ?></p>
                        <p><?php echo $action ?></p>
                    </div>
					<?php
				}
				?>
            </div>
        </div>
		<?php
	}

	public function handle_point_adjustment() {
		// check nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'rogershood_adjust_points_nonce' ) ) {
			wp_die( 'Invalid nonce' );
		}

		// check if is admin
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to do this' );
		}

		$user_id           = intval( $_POST['user_id'] );
		$new_point_balance = intval( $_POST['points'] );
		// get point
		$points = new Rogershood_Points( $user_id );

		if ( $points->get_current_points() > $new_point_balance ) {
			// deduct points
			$points_to_deduct = $points->get_current_points() - $new_point_balance;
			$points->reverse_earned_points( $points_to_deduct );

			$transaction = new Rogershood_Points_Transaction( $user_id, $points_to_deduct, 'debit', 'admin_adjustment' );
			$transaction->save();
			wp_safe_redirect( admin_url( 'admin.php?page=rogershood_user_points_edit&user_id=' . $user_id . '&points_updated=1' ) );

		} else if ( $points->get_current_points() < $new_point_balance ) {
			$points_to_add = $new_point_balance - $points->get_current_points();
			// add points
			$points->earn_points( $points_to_add );
			$transaction = new Rogershood_Points_Transaction( $user_id, $points_to_add, 'credit', 'admin_adjustment' );
			$transaction->save();
			wp_safe_redirect( admin_url( 'admin.php?page=rogershood_user_points_edit&user_id=' . $user_id . '&points_updated=1' ) );

		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=rogershood_user_points_edit&user_id=' . $user_id ) );
		}

	}

	public function render_points_settings_page() {

		$points_settings            = get_option( 'rogershood_points_settings' );
		$points_earned_per_dollar   = $points_settings['points_earned_per_dollar'] ?? 1;
		$points_per_dollar_redeemed = $points_settings['points_per_dollar_redeemed'] ?? 1;
		$points_upon_signup         = $points_settings['points_upon_signup'] ?? 1;

		$admin_post_url = admin_url( 'admin-post.php' );

		?>
		<?php if ( isset( $_GET['updated'] ) && ( $_GET['updated'] ) === '1' ): ?>
            <div id="message" class="updated notice is-dismissible">
                <p>Points settings successfully.</p>
            </div>
		<?php endif; ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Points Settings', 'rogershood-points' ); ?></h1>
            <form action="<?php echo $admin_post_url ?>" method="post">
                <input type="hidden" name="action" value="save_points_settings">

				<?php wp_nonce_field( 'save_points_settings', 'rogershood_points_settings_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="oints_earned_er_dollar"><?php esc_html_e( 'Points earned per $1', 'rogershood-points' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="points_earned_per_dollar" id="points_earned_per_dollar"
                                   value="<?php echo esc_attr( $points_earned_per_dollar ); ?>" min="0"/>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="points_per_dollar_redeemed"><?php esc_html_e( 'Points per $1 redeemed', 'rogershood-points' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="points_per_dollar_redeemed" id="points_per_dollar_redeemed"
                                   value="<?php echo esc_attr( $points_per_dollar_redeemed ); ?>" min="0"/>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="points_upon_signup"><?php esc_html_e( 'Points upon signup', 'rogershood-points' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="points_upon_signup" id="points_upon_signup"
                                   value="<?php echo esc_attr( $points_upon_signup ); ?>" min="0"/>
                        </td>
                    </tr>
                </table>

				<?php submit_button( __( 'Save Settings', 'rogershood-points' ) ); ?>
            </form>
        </div>
		<?php
	}

	public function handle_points_settings_form() {
		if ( isset( $_POST['rogershood_points_settings_nonce'] ) && wp_verify_nonce( $_POST['rogershood_points_settings_nonce'], 'save_points_settings' ) && current_user_can( 'manage_options' ) ) {
			// Prepare the array to save both settings
			$points_settings = array(
				'points_earned_per_dollar'   => isset( $_POST['points_earned_per_dollar'] ) ? absint( $_POST['points_earned_per_dollar'] ) : 1,
				'points_per_dollar_redeemed' => isset( $_POST['points_per_dollar_redeemed'] ) ? absint( $_POST['points_per_dollar_redeemed'] ) : 1,
				'points_upon_signup'         => isset( $_POST['points_upon_signup'] ) ? absint( $_POST['points_upon_signup'] ) : 1,
			);

			// Save the options as a single entry in the database
			update_option( 'rogershood_points_settings', $points_settings );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=rogershood_points_settings&updated=1' ) );
	}

}
