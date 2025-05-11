jQuery(document).ready(function($) {
    // Handle city selection
    $('#dekapost_city_id').on('change', function() {
        var cityId = $(this).val();
        if (cityId) {
            loadContracts(cityId);
        } else {
            $('#dekapost_contract_id').html('<option value="">Select a city first</option>');
        }
    });

    // Function to load contracts
    function loadContracts(cityId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dekapost_get_contracts',
                nonce: dekapostAdmin.nonce,
                city_id: cityId
            },
            beforeSend: function() {
                $('#dekapost_contract_id').html('<option value="">Loading contracts...</option>');
            },
            success: function(response) {
                if (response.success && response.data) {
                    var options = '<option value="">Select a contract</option>';
                    response.data.forEach(function(contract) {
                        options += '<option value="' + contract.id + '">' + contract.name + '</option>';
                    });
                    $('#dekapost_contract_id').html(options);
                } else {
                    $('#dekapost_contract_id').html('<option value="">No contracts found</option>');
                }
            },
            error: function() {
                $('#dekapost_contract_id').html('<option value="">Error loading contracts</option>');
            }
        });
    }

    // Handle Excel file upload
    $('#dekapost-excel-upload').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'dekapost_upload_excel');
        formData.append('nonce', dekapostAdmin.nonce);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $('#dekapost-upload-status').html('Uploading...');
            },
            success: function(response) {
                if (response.success) {
                    $('#dekapost-upload-status').html('File uploaded successfully. Processing...');
                    processExcelData(response.data);
                } else {
                    $('#dekapost-upload-status').html('Error: ' + (response.data.message || 'Upload failed'));
                }
            },
            error: function() {
                $('#dekapost-upload-status').html('Error: Upload failed');
            }
        });
    });

    function processExcelData(data) {
        // Create table with the data
        var table = '<table class="wp-list-table widefat fixed striped">';
        table += '<thead><tr>';
        table += '<th>Recipient Name</th>';
        table += '<th>Phone</th>';
        table += '<th>Address</th>';
        table += '<th>City</th>';
        table += '<th>Weight (kg)</th>';
        table += '<th>Dimensions (cm)</th>';
        table += '</tr></thead><tbody>';
        
        data.forEach(function(row) {
            table += '<tr>';
            table += '<td>' + row.recipient_name + '</td>';
            table += '<td>' + row.phone + '</td>';
            table += '<td>' + row.address + '</td>';
            table += '<td>' + row.city + '</td>';
            table += '<td>' + row.weight + '</td>';
            table += '<td>' + row.dimensions + '</td>';
            table += '</tr>';
        });
        
        table += '</tbody></table>';
        
        $('#dekapost-parcels-table').html(table);
        $('#dekapost-save-parcels').show();
    }

    // Handle saving parcels
    $('#dekapost-save-parcels').on('click', function() {
        var parcels = [];
        $('#dekapost-parcels-table tbody tr').each(function() {
            var row = $(this);
            parcels.push({
                recipient_name: row.find('td:eq(0)').text(),
                phone: row.find('td:eq(1)').text(),
                address: row.find('td:eq(2)').text(),
                city: row.find('td:eq(3)').text(),
                weight: row.find('td:eq(4)').text(),
                dimensions: row.find('td:eq(5)').text()
            });
        });

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dekapost_save_parcels',
                nonce: dekapostAdmin.nonce,
                parcels: parcels
            },
            beforeSend: function() {
                $('#dekapost-upload-status').html('Saving parcels...');
            },
            success: function(response) {
                if (response.success) {
                    $('#dekapost-upload-status').html('Parcels saved successfully!');
                    $('#dekapost-parcels-table').empty();
                    $('#dekapost-save-parcels').hide();
                } else {
                    $('#dekapost-upload-status').html('Error: ' + (response.data.message || 'Failed to save parcels'));
                }
            },
            error: function() {
                $('#dekapost-upload-status').html('Error: Failed to save parcels');
            }
        });
    });

    function handleExcelUpload() {
        const fileInput = document.getElementById('excel_file');
        const file = fileInput.files[0];
        
        if (!file) {
            alert('Please select a file to upload');
            return;
        }

        // Check file type
        const fileType = file.name.split('.').pop().toLowerCase();
        if (!['xlsx', 'xls'].includes(fileType)) {
            alert('Please upload an Excel file (.xlsx or .xls)');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'dekapost_upload_excel');
        formData.append('nonce', dekapostShipping.nonce);
        formData.append('excel_file', file);

        // Show loading state
        const uploadButton = document.getElementById('upload_button');
        const originalText = uploadButton.textContent;
        uploadButton.disabled = true;
        uploadButton.textContent = 'Uploading...';

        jQuery.ajax({
            url: dekapostShipping.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Upload response:', response);
                if (response.success) {
                    // Store the processed data
                    window.parcelsData = response.data.parcels;
                    window.priceData = response.data.data;
                    
                    // Show success message
                    alert(response.data.message || 'File uploaded successfully');
                    
                    // Enable save button
                    document.getElementById('save_button').disabled = false;
                } else {
                    console.error('Upload error:', response);
                    alert(response.data.message || 'Error uploading file');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {xhr, status, error});
                alert('Error uploading file: ' + error);
            },
            complete: function() {
                // Reset button state
                uploadButton.disabled = false;
                uploadButton.textContent = originalText;
            }
        });
    }
}); 