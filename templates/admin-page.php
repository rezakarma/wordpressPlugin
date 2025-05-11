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
            <div class="form-field" id="contract-field" style="display: none;">
                <label for="contract"><?php echo esc_html__('Select Contract', 'dekapost-shipping'); ?></label>
                <select id="contract" name="contract" disabled>
                    <option value=""><?php echo esc_html__('Select a contract', 'dekapost-shipping'); ?></option>
                </select>
            </div>

            <!-- Payment Type Selection -->
            <div class="form-field" id="payment-type-field" style="display: none;">
                <label for="payment-type"><?php echo esc_html__('Select Payment Type', 'dekapost-shipping'); ?></label>
                <select id="payment-type" name="payment-type" disabled>
                    <option value=""><?php echo esc_html__('Select a payment type', 'dekapost-shipping'); ?></option>
                </select>
            </div>

            <!-- Excel Upload -->
            <div class="form-field" id="excel-upload-field" style="display: none;">
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

<script>
jQuery(document).ready(function($) {
    // City selection change
    $('#city').on('change', function() {
        var cityId = $(this).val();
        if (cityId) {
            // Show contract field
            $('#contract-field').show();
            $('#contract').prop('disabled', false);
            
            // Load contracts
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dekapost_get_contracts',
                    city_id: cityId,
                    nonce: dekapostShipping.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var contracts = response.data;
                        var $contract = $('#contract');
                        $contract.empty().append('<option value="">Select a contract</option>');
                        
                        contracts.forEach(function(contract) {
                            $contract.append(
                                $('<option></option>')
                                    .val(contract.ID)
                                    .text(contract.ContractTitle)
                                    .data('pay-type', contract.payType)
                            );
                        });
                    } else {
                        alert(response.data.message || 'Failed to load contracts');
                    }
                }
            });
        } else {
            // Hide and reset contract field
            $('#contract-field').hide();
            $('#contract').prop('disabled', true).empty();
            $('#payment-type-field').hide();
            $('#payment-type').prop('disabled', true).empty();
            $('#excel-upload-field').hide();
        }
    });

    // Contract selection change
    $('#contract').on('change', function() {
        var $selected = $(this).find('option:selected');
        var payType = $selected.data('pay-type');
        
        if (payType) {
            // Show payment type field
            $('#payment-type-field').show();
            $('#payment-type').prop('disabled', false);
            
            // Parse and populate payment types
            var payTypes = JSON.parse(payType).payTypeList;
            var $paymentType = $('#payment-type');
            $paymentType.empty().append('<option value="">Select a payment type</option>');
            
            payTypes.forEach(function(type) {
                $paymentType.append(
                    $('<option></option>')
                        .val(type.payTypeID)
                        .text(type.payTypeName)
                );
            });
        } else {
            // Hide and reset payment type field
            $('#payment-type-field').hide();
            $('#payment-type').prop('disabled', true).empty();
            $('#excel-upload-field').hide();
        }
    });

    // Payment type selection change
    $('#payment-type').on('change', function() {
        if ($(this).val()) {
            $('#excel-upload-field').show();
        } else {
            $('#excel-upload-field').hide();
        }
    });

    // Excel upload
    $('#upload-excel').on('click', function() {
        var file = $('#excel_file')[0].files[0];
        if (!file) {
            alert('Please select a file first');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'dekapost_upload_excel');
        formData.append('excel_file', file);
        formData.append('nonce', dekapostShipping.nonce);
        formData.append('city_id', $('#city').val());
        formData.append('contract_id', $('#contract').val());
        formData.append('payment_type_id', $('#payment-type').val());

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Show results table
                    $('.results-table-container').show();
                    
                    // Populate results
                    var $tbody = $('#parcels-results');
                    $tbody.empty();
                    
                    response.data.data.forEach(function(parcel) {
                        var row = $('<tr></tr>');
                        row.append('<td><input type="checkbox" class="parcel-checkbox"></td>');
                        row.append('<td>' + parcel.weight + '</td>');
                        row.append('<td>' + parcel.sourceCity + '</td>');
                        row.append('<td>' + parcel.destCity + '</td>');
                        row.append('<td>' + parcel.totalAmount + '</td>');
                        row.append('<td>' + parcel.message + '</td>');
                        $tbody.append(row);
                    });
                } else {
                    alert(response.data.message || 'Failed to process Excel file');
                }
            }
        });
    });

    // Select all parcels
    $('#select-all-parcels').on('change', function() {
        $('.parcel-checkbox').prop('checked', $(this).prop('checked'));
    });

    // Submit selected parcels
    $('#submit-parcels').on('click', function() {
        var selectedParcels = [];
        $('.parcel-checkbox:checked').each(function() {
            var index = $(this).closest('tr').index();
            selectedParcels.push(parcelsData[index]);
        });

        if (selectedParcels.length === 0) {
            alert('Please select at least one parcel');
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dekapost_save_parcels',
                parcels_data: JSON.stringify(selectedParcels),
                nonce: dekapostShipping.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Parcels saved successfully');
                    $('.results-table-container').hide();
                    $('#excel_file').val('');
                } else {
                    alert(response.data.message || 'Failed to save parcels');
                }
            }
        });
    });

    // Delete selected parcels
    $('#delete-selected').on('click', function() {
        $('.parcel-checkbox:checked').closest('tr').remove();
        if ($('.parcel-checkbox').length === 0) {
            $('.results-table-container').hide();
        }
    });
});
</script> 