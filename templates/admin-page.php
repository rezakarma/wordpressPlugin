<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('Dekapost Shipping', 'dekapost-shipping'); ?></h1>

    <div class="dekapost-shipping-container">
        <!-- Authentication Section -->
        <div class="dekapost-section">
            <h2><?php echo esc_html__('API Authentication', 'dekapost-shipping'); ?></h2>
            <form method="post" action="options.php">
                <?php settings_fields('dekapost_shipping_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="api_username"><?php echo esc_html__('API Username', 'dekapost-shipping'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="api_username" name="dekapost_shipping_settings[api_username]" 
                                   value="<?php echo esc_attr($settings['api_username'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="api_password"><?php echo esc_html__('API Password', 'dekapost-shipping'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="api_password" name="dekapost_shipping_settings[api_password]" 
                                   value="<?php echo esc_attr($settings['api_password'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save Settings', 'dekapost-shipping')); ?>
            </form>
        </div>

        <!-- Parcel Management Section -->
        <div class="dekapost-section">
            <h2><?php echo esc_html__('Parcel Management', 'dekapost-shipping'); ?></h2>
            
            <!-- City Selection -->
            <div class="form-field">
                <label for="city"><?php echo esc_html__('Select City', 'dekapost-shipping'); ?></label>
                <select id="city" name="city">
                    <option value=""><?php echo esc_html__('Select a city', 'dekapost-shipping'); ?></option>
                    <?php if ($cities && isset($cities['addressData']) && is_array($cities['addressData'])) : ?>
                        <?php foreach ($cities['addressData'] as $city) : ?>
                            <option value="<?php echo esc_attr($city['cityID']); ?>">
                                <?php echo esc_html($city['cityName']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Contract Selection -->
            <div class="form-field">
                <label for="contract"><?php echo esc_html__('Select Contract', 'dekapost-shipping'); ?></label>
                <select id="contract" name="contract">
                    <option value=""><?php echo esc_html__('Select a contract', 'dekapost-shipping'); ?></option>
                    <?php if ($contracts && is_array($contracts)) : ?>
                        <?php foreach ($contracts as $contract) : ?>
                            <option value="<?php echo esc_attr($contract['ID']); ?>">
                                <?php echo esc_html($contract['ContractTitle']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Excel Upload -->
            <div class="form-field">
                <label for="excel_file"><?php echo esc_html__('Upload Excel File', 'dekapost-shipping'); ?></label>
                <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls">
                <button type="button" class="button" id="upload-excel">
                    <?php echo esc_html__('Upload and Calculate', 'dekapost-shipping'); ?>
                </button>
                <p class="description">
                    <?php echo esc_html__('Upload an Excel file with parcel information. The file should contain columns for weight, source city, destination city, and content amount.', 'dekapost-shipping'); ?>
                </p>
            </div>

            <!-- Results Table -->
            <div class="results-table-container" style="display: none;">
                <h3><?php echo esc_html__('Calculation Results', 'dekapost-shipping'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" id="select-all-parcels">
                            </th>
                            <th><?php echo esc_html__('Weight', 'dekapost-shipping'); ?></th>
                            <th><?php echo esc_html__('Source City', 'dekapost-shipping'); ?></th>
                            <th><?php echo esc_html__('Destination City', 'dekapost-shipping'); ?></th>
                            <th><?php echo esc_html__('Total Amount', 'dekapost-shipping'); ?></th>
                            <th><?php echo esc_html__('Status', 'dekapost-shipping'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="parcels-results">
                        <!-- Results will be populated via JavaScript -->
                    </tbody>
                </table>
                <div class="submit-buttons">
                    <button type="button" class="button button-primary" id="submit-parcels">
                        <?php echo esc_html__('Submit Selected Parcels', 'dekapost-shipping'); ?>
                    </button>
                    <button type="button" class="button" id="delete-selected">
                        <?php echo esc_html__('Delete Selected', 'dekapost-shipping'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div> 