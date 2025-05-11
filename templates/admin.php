<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="dekapost-admin-container">
        <div class="dekapost-admin-section">
            <h2>API Settings</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('dekapost_shipping_options');
                do_settings_sections('dekapost_shipping_options');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="dekapost_api_url">API URL</label>
                        </th>
                        <td>
                            <input type="url" id="dekapost_api_url" name="dekapost_api_url" 
                                   value="<?php echo esc_attr(get_option('dekapost_api_url')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="dekapost_username">Username</label>
                        </th>
                        <td>
                            <input type="text" id="dekapost_username" name="dekapost_username" 
                                   value="<?php echo esc_attr(get_option('dekapost_username')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="dekapost_password">Password</label>
                        </th>
                        <td>
                            <input type="password" id="dekapost_password" name="dekapost_password" 
                                   value="<?php echo esc_attr(get_option('dekapost_password')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="dekapost_city_id">City</label>
                        </th>
                        <td>
                            <select id="dekapost_city_id" name="dekapost_city_id" class="regular-text">
                                <option value="">Select a city</option>
                                <?php
                                $cities = get_option('dekapost_cities', array());
                                $selected_city = get_option('dekapost_city_id');
                                foreach ($cities as $city) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($city['id']),
                                        selected($selected_city, $city['id'], false),
                                        esc_html($city['name'])
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="dekapost_contract_id">Contract</label>
                        </th>
                        <td>
                            <select id="dekapost_contract_id" name="dekapost_contract_id" class="regular-text">
                                <option value="">Select a city first</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>

        <div class="dekapost-admin-section">
            <h2>Upload Parcels</h2>
            <form id="dekapost-excel-upload" enctype="multipart/form-data">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="dekapost_excel_file">Excel File</label>
                        </th>
                        <td>
                            <input type="file" id="dekapost_excel_file" name="dekapost_excel_file" accept=".xlsx,.xls" required>
                            <p class="description">Upload an Excel file containing parcel information.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Upload File', 'primary', 'submit', false); ?>
            </form>
            <div id="dekapost-upload-status"></div>
            <div id="dekapost-parcels-table"></div>
            <button id="dekapost-save-parcels" class="button button-primary" style="display: none;">Save Parcels</button>
        </div>
    </div>
</div>

<style>
.dekapost-admin-container {
    margin-top: 20px;
}

.dekapost-admin-section {
    background: #fff;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.dekapost-admin-section h2 {
    margin-top: 0;
    padding-bottom: 12px;
    border-bottom: 1px solid #eee;
}

#dekapost-upload-status {
    margin: 15px 0;
    padding: 10px;
    background: #f8f8f8;
    border-left: 4px solid #ddd;
}

#dekapost-parcels-table {
    margin: 15px 0;
}

#dekapost-save-parcels {
    margin-top: 15px;
}
</style> 