jQuery(document).ready(function($) {
    // Initially hide the contract select box
    $('#dekapost_contract_id').hide();
    
    // Handle city selection
    $('#dekapost_city_id').on('change', function() {
        var cityId = $(this).val();
        console.log('City selected:', cityId);
        
        // Hide contract select box if no city is selected
        if (!cityId) {
            $('#dekapost_contract_id').hide().html('<option value="">Select a city first</option>');
            return;
        }
        
        // Show loading state
        $('#dekapost_contract_id').show().html('<option value="">Loading contracts...</option>');
        
        // Load contracts for selected city
        loadContracts(cityId);
    });

    // Function to load contracts
    function loadContracts(cityId) {
        console.log('Loading contracts for city:', cityId);
        $.ajax({
            url: dekapostShipping.ajaxurl,
            type: 'POST',
            data: {
                action: 'dekapost_get_contracts',
                nonce: dekapostShipping.nonce,
                city_id: cityId
            },
            success: function(response) {
                console.log('Raw contracts response:', response);
                
                if (response.success && response.data && response.data.contracts) {
                    var contracts = response.data.contracts;
                    console.log('Contracts data:', contracts);
                    
                    var options = '<option value="">Select a contract</option>';
                    
                    if (Array.isArray(contracts)) {
                        contracts.forEach(function(contract) {
                            console.log('Processing contract:', contract);
                            if (contract && contract.ID && contract.ContractTitle) {
                                var contractTitle = contract.ContractTitle;
                                var serviceName = contract.ServiceName || '';
                                options += '<option value="' + contract.ID + '">' + contractTitle + ' - ' + serviceName + '</option>';
                            }
                        });
                    } else {
                        console.error('Contracts data is not an array:', contracts);
                    }
                    
                    // Update the select box with the new options
                    $('#dekapost_contract_id').html(options);
                    console.log('Updated contract select box with options:', options);
                } else {
                    console.error('Invalid response:', response);
                    $('#dekapost_contract_id').html('<option value="">No contracts found</option>');
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