<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors(); ?>

    <div class="dekapost-admin-container">
        <!-- API Settings Form -->
        <div class="dekapost-section">
            <h2><?php _e('API Settings', 'dekapost-shipping'); ?></h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('dekapost_shipping_settings');
                do_settings_sections('dekapost_shipping_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="dekapost_api_username"><?php _e('API Username', 'dekapost-shipping'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="dekapost_api_username" name="dekapost_shipping_settings[api_username]" 
                                value="<?php echo esc_attr($settings['api_username'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="dekapost_api_password"><?php _e('API Password', 'dekapost-shipping'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="dekapost_api_password" name="dekapost_shipping_settings[api_password]" 
                                value="<?php echo esc_attr($settings['api_password'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>

        <!-- Excel Upload Form -->
        <div class="dekapost-section">
            <h2><?php _e('Upload Parcels', 'dekapost-shipping'); ?></h2>
            <div class="dekapost-upload-form">
                <div class="form-group">
                    <label for="dekapost_city_id"><?php _e('Source City', 'dekapost-shipping'); ?></label>
                    <select id="dekapost_city_id" name="city_id" class="regular-text">
                        <option value=""><?php _e('Select a city', 'dekapost-shipping'); ?></option>
                        <?php
                        if (!empty($cities)) {
                            foreach ($cities as $city) {
                                printf(
                                    '<option value="%s">%s - %s</option>',
                                    esc_attr($city['id']),
                                    esc_html($city['name']),
                                    esc_html($city['stateName'])
                                );
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="dekapost_contract_id"><?php _e('Contract', 'dekapost-shipping'); ?></label>
                    <select id="dekapost_contract_id" name="contract_id" class="regular-text">
                        <option value=""><?php _e('Select a city first', 'dekapost-shipping'); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="excel_file"><?php _e('Excel File', 'dekapost-shipping'); ?></label>
                    <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls" class="regular-text">
                    <p class="description">
                        <?php _e('Upload an Excel file containing parcel information. The file should have columns for weight, source city, destination city, and content amount.', 'dekapost-shipping'); ?>
                    </p>
                </div>

                <div class="form-group">
                    <button type="button" id="upload_button" class="button button-primary">
                        <?php _e('Upload and Calculate', 'dekapost-shipping'); ?>
                    </button>
                    <button type="button" id="save_button" class="button button-secondary" disabled>
                        <?php _e('Save Parcels', 'dekapost-shipping'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dekapost-admin-container {
    margin-top: 20px;
}

.dekapost-section {
    background: #fff;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.dekapost-upload-form {
    max-width: 600px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.form-group .description {
    color: #666;
    font-style: italic;
    margin-top: 5px;
}

#save_button {
    margin-left: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // City selection change
    $('#dekapost_city_id').on('change', function() {
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
        formData.append('city_id', $('#dekapost_city_id').val());
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