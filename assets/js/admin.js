jQuery(document).ready(function($) {
    // Handle city selection
    $('#dekapost_city_id').on('change', function() {
        var cityId = $(this).val();
        if (cityId) {
            // Show contract field and load contracts
            $('#dekapost_contract_id').prop('disabled', false);
            loadContracts(cityId);
        } else {
            // Hide and reset contract field
            $('#dekapost_contract_id').prop('disabled', true)
                .html('<option value="">Select a city first</option>');
        }
    });

    // Function to load contracts
    function loadContracts(cityId) {
        $.ajax({
            url: dekapostShipping.ajaxurl,
            type: 'POST',
            data: {
                action: 'dekapost_get_contracts',
                nonce: dekapostShipping.nonce,
                city_id: cityId
            },
            beforeSend: function() {
                $('#dekapost_contract_id').html('<option value="">Loading contracts...</option>');
            },
            success: function(response) {
                console.log('Contracts response:', response);
                if (response.success && response.data) {
                    var options = '<option value="">Select a contract</option>';
                    response.data.forEach(function(contract) {
                        options += '<option value="' + contract.id + '">' + contract.name + '</option>';
                    });
                    $('#dekapost_contract_id').html(options);
                } else {
                    $('#dekapost_contract_id').html('<option value="">No contracts found</option>');
                    if (response.data && response.data.message) {
                        console.error('Contract loading error:', response.data.message);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Contract loading error:', {xhr, status, error});
                $('#dekapost_contract_id').html('<option value="">Error loading contracts</option>');
            }
        });
    }

    // Handle Excel file upload
    $('#upload_button').on('click', function(e) {
        e.preventDefault();
        
        const fileInput = document.getElementById('excel_file');
        const file = fileInput.files[0];
        const cityId = $('#dekapost_city_id').val();
        const contractId = $('#dekapost_contract_id').val();
        
        if (!cityId) {
            alert('Please select a city first');
            return;
        }

        if (!contractId) {
            alert('Please select a contract');
            return;
        }
        
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
        formData.append('city_id', cityId);
        formData.append('contract_id', contractId);

        // Show loading state
        const uploadButton = document.getElementById('upload_button');
        const originalText = uploadButton.textContent;
        uploadButton.disabled = true;
        uploadButton.textContent = 'Uploading...';

        $.ajax({
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
    });

    // Handle saving parcels
    $('#save_button').on('click', function() {
        if (!window.parcelsData) {
            alert('No parcels data to save');
            return;
        }

        $.ajax({
            url: dekapostShipping.ajaxurl,
            type: 'POST',
            data: {
                action: 'dekapost_save_parcels',
                nonce: dekapostShipping.nonce,
                parcels_data: JSON.stringify(window.parcelsData)
            },
            beforeSend: function() {
                $('#save_button').prop('disabled', true).text('Saving...');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || 'Parcels saved successfully');
                    window.parcelsData = null;
                    window.priceData = null;
                    document.getElementById('excel_file').value = '';
                } else {
                    alert(response.data.message || 'Failed to save parcels');
                }
            },
            error: function(xhr, status, error) {
                console.error('Save error:', {xhr, status, error});
                alert('Error saving parcels: ' + error);
            },
            complete: function() {
                $('#save_button').prop('disabled', false).text('Save Parcels');
            }
        });
    });
}); 