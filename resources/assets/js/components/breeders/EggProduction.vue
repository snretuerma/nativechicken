<template>
    <div class="row">
        <div class="col s12 m12 l12">
            <div class="card-panel blue-grey lighten-5">
                <!-- Egg Production Record -->
                <div class="row" v-show="hide_record==false">
                    <div class="col s6 m6 l6">
                        <h5 v-if="breeder.breeder_code!==null">Egg Production | {{breeder.breeder_code}}</h5>
                        <h5 v-else>Egg Production | {{breeder.breeder_tag}}</h5>
                        <i>Generation: <strong>{{breeder.generation_number}}</strong> Line: <strong>{{breeder.line_number}}</strong> Family: <strong>{{breeder.family_number}}</strong> Pen: <strong>{{breeder.pen_number}}</strong></i>
                    </div>
                    <div class="col s3 m3 l3 right-align">
                        <a @click="add_record=true; hide_record=true" class="waves-effect waves-green btn-flat green-text"><i class="fas fa-plus-circle left"></i>Add</a>
                    </div>
                    <div class="col s3 m3 l3 center-align">
                        <a v-on:click="closeEggProduction" class="waves-effect waves-red btn-flat red-text"><i class="far fa-times-circle left"></i>Close</a>
                    </div>
                </div>
                <div v-show="hide_record==false">
                    <div class="row">
                        <div class="col s12 m12 l12">
                            <table class="responsive-table bordered highlight centered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Total Intact</th>
                                        <th>Total Weight</th>
                                        <th>Average Weight</th>
                                        <th>Broken</th>
                                        <th>Rejected</th>
                                        <th>Remarks</th>
                                        <th><i class="fas fa-edit"></i></th>
                                        <th><i class="fas fa-trash-alt"></i></th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr v-if="eggprod_length == 0">
                                        <td>-</td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>-</td>
                                    </tr>
                                    <tr v-else v-for="prod in eggprod.data" :key="prod.eggprod_id">
                                        <td>{{prod.date_collected}}</td>
                                        <td>{{prod.total_eggs_intact}}</td>
                                        <td>{{prod.total_egg_weight}}</td>
                                        <td>{{(prod.total_egg_weight/prod.total_eggs_intact).toFixed(3)}}</td>
                                        <td>{{prod.total_broken}}</td>
                                        <td>{{prod.total_rejects}}</td>
                                        <td>{{prod.remarks}}</td>
                                        <td><a @click="initializeEdit(prod)" href="#edit_eggprod" class="modal-trigger"><i class="fas fa-edit"></i></a></td>
                                        <td><a @click="selected_eggprod_record = prod" href="#delete_eggprod" class="modal-trigger"><i class="fas fa-trash-alt"></i></a></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col s12 m12 l12 center">
                            <pagination :data="eggprod" @pagination-change-page="fetchEggProductionRecord"></pagination>
                        </div>
                    </div>
                </div>

                <!-- Egg Production Form -->
                <div class="row" v-show="add_record!=null">
                    <div class="col s12 m12 l12">
                        <form v-on:submit.prevent="validateInput" method="post">
                            <div class="row valign-wrapper">
                                <div class="col s9 m19 l9">
                                    <h5 v-if="breeder.breeder_code!==null">Egg Production | {{breeder.breeder_code}}</h5>
                                    <h5 v-else>Egg Production | {{breeder.breeder_tag}}</h5>
                                    <i>Generation: <strong>{{breeder.generation_number}}</strong> Line: <strong>{{breeder.line_number}}</strong> Family: <strong>{{breeder.family_number}}</strong> Pen: <strong>{{breeder.pen_number}}</strong></i>
                                </div>
                                <div class="col s3 m3 l3 center-align">
                                    <a @click="add_record=null; hide_record=false" class="waves-effect waves-red btn-flat red-text"><i class="far fa-times-circle left"></i>Close</a>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col s12 l6">
                                    <label for="date_added">Date Collected <span v-if="check_date_collected===false" class="red-text"><i><i class="fas fa-exclamation-circle"></i>Input is required</i></span></label>
                                    <datepicker placeholder="Date when data was gathered" id="date_added" :format="dateFormatter" v-model="date_collected"></datepicker>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col s12 l6 input-field">
                                    <label class="active" for="intact">Total Eggs Intact  <span v-if="check_total_eggs_intact===false" class="red-text"><i><i class="fas fa-exclamation-circle"></i>Input is required</i></span></label>
                                    <input v-model.number="total_eggs_intact" class="validate" placeholder="Total Eggs that passes as good" id="intact" type="number" min="0" validate onkeypress="return (event.charCode == 8 || event.charCode == 0 || event.charCode == 13) ? null : event.charCode >= 48 && event.charCode <= 57">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col s12 l6 input-field">
                                    <input v-model.number="total_egg_weight" class="validate" placeholder="Totall egg weight of all intact eggs" id="total_weight" type="number" min="0" step="0.001" pattern="^\d*(\.\d{0,3})?$" onkeypress="return event.charCode != 45">
                                    <label class="active" for="total_weight">Total Egg Weight (g) <span v-if="check_total_egg_weight===false" class="red-text"><i><i class="fas fa-exclamation-circle"></i>Input is required</i></span></label>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col s12 l6 input-field">
                                    <input v-model.number="total_broken" class="validate" placeholder="Total Eggs that were broken" id="total_broken" type="number" min="0" validate onkeypress="return (event.charCode == 8 || event.charCode == 0 || event.charCode == 13) ? null : event.charCode >= 48 && event.charCode <= 57">
                                    <label class="active" for="total_broken">Total Eggs Broken <span v-if="check_total_broken===false" class="red-text"><i><i class="fas fa-exclamation-circle"></i>Input is required</i></span></label>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col s12 l6 input-field">
                                    <input v-model.number="total_rejects" class="validate" placeholder="Total Eggs that doesn't pass as good condition" id="total_rejects" type="number" min="0" validate onkeypress="return (event.charCode == 8 || event.charCode == 0 || event.charCode == 13) ? null : event.charCode >= 48 && event.charCode <= 57">
                                    <label class="active" for="total_rejects">Total Eggs Rejected <span v-if="check_total_rejects===false" class="red-text"><i><i class="fas fa-exclamation-circle"></i>Input is required</i></span></label>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col s12 l6 input-field">
                                    <input v-model="remarks" placeholder="Add remarks" id="egg_prod_remarks" type="text">
                                    <label class="active" for="egg_prod_remarks">Remarks <i>(Optional)</i></label>
                                </div>
                            </div>
                            <div class="row center">
                                <div class="col s12 m12 l12">
                                    <button class="btn waves-effect waves-light blue-grey" type="submit">Submit</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="edit_eggprod" class="modal modal-fixed-footer">
                    <form v-on:submit.prevent="validateEdit" method="patch">
                        <div class="modal-content">
                            <h5 v-if="breeder.breeder_code!==null">Edit Egg Production | {{breeder.breeder_code}}</h5>
                            <h5 v-else>Edit Egg Production | {{breeder.breeder_tag}}</h5>
                            <div class="divider"></div>
                            <div class="row">
                                <div class="col s12 l6">
                                    <label for="edit_date_added">Date Collected <span v-if="edit_check_date_collected===false" class="red-text"><i><i class="fas fa-exclamation-circle"></i>Input is required</i></span></label>
                                    <datepicker placeholder="Date when data was gathered" id="edit_date_added" :format="dateFormatter" v-model="edit_date_collected"></datepicker>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col s12 l6 input-field">
                                    <label class="active" for="edit_intact">Total Eggs Intact  <span v-if="edit_check_total_eggs_intact===false" class="red-text"><i><i class="fas fa-exclamation-circle"></i>Input is required</i></span></label>
                                    <input v-model.number="edit_total_eggs_intact" class="validate" placeholder="Total Eggs that passes as good" id="edit_intact" type="number" min="0" validate onkeypress="return (event.charCode == 8 || event.charCode == 0 || event.charCode == 13) ? null : event.charCode >= 48 && event.charCode <= 57">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col s12 l6 input-field">
                                    <input v-model.number="edit_total_egg_weight" class="validate" placeholder="Totall egg weight of all intact eggs" id="edit_total_weight" type="number" min="0" step="0.001" pattern="^\d*(\.\d{0,3})?$" onkeypress="return event.charCode != 45">
                                    <label class="active" for="edit_total_weight">Total Egg Weight (g) <span v-if="edit_check_total_egg_weight===false" class="red-text"><i><i class="fas fa-exclamation-circle"></i>Input is required</i></span></label>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col s12 l6 input-field">
                                    <input v-model.number="edit_total_broken" class="validate" placeholder="Total Eggs that were broken" id="edit_total_broken" type="number" min="0" validate onkeypress="return (event.charCode == 8 || event.charCode == 0 || event.charCode == 13) ? null : event.charCode >= 48 && event.charCode <= 57">
                                    <label class="active" for="edit_total_broken">Total Eggs Broken <span v-if="edit_check_total_broken===false" class="red-text"><i><i class="fas fa-exclamation-circle"></i>Input is required</i></span></label>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col s12 l6 input-field">
                                    <input v-model.number="edit_total_rejects" class="validate" placeholder="Total Eggs that doesn't pass as good condition" id="edit_total_rejects" type="number" min="0" validate onkeypress="return (event.charCode == 8 || event.charCode == 0 || event.charCode == 13) ? null : event.charCode >= 48 && event.charCode <= 57">
                                    <label class="active" for="edit_total_rejects">Total Eggs Rejected <span v-if="edit_check_total_rejects===false" class="red-text"><i><i class="fas fa-exclamation-circle"></i>Input is required</i></span></label>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col s12 l6 input-field">
                                    <input v-model="edit_remarks" placeholder="Add remarks" id="edit_egg_prod_remarks" type="text">
                                    <label class="active" for="edit_egg_prod_remarks">Remarks <i>(Optional)</i></label>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-flat waves-effect waves-light" type="submit">Edit</button>
                            <a @click="uninitializeEdit()" href="javascript:void(0)" class="modal-action modal-close waves-effect waves-light btn-flat">Close</a>
                        </div>
                    </form>
                </div>

                <div id="delete_eggprod" class="modal modal-fixed-footer">
                    <div class="modal-content">
                        <div class="row">
                            <div class="col s12 m12 l12">
                                <h4>Delete Record</h4>
                            </div>
                        </div>
                        <div class="row valign-wrapper">
                            <div class="col s2 m2 l2 red-text center-align">
                                <h4><i class="fas fa-exclamation-triangle"></i></h4>
                            </div>
                            <div class="col s10 m10 l10">
                                <p>Are you sure you want to <strong>Delete</strong> this egg production record?</p>
                                <blockquote>
                                    <span>Date : {{selected_eggprod_record.date_collected}}</span><br>
                                    <span>Total Intact : {{selected_eggprod_record.total_eggs_intact}}</span><br>
                                    <span>Total Weight : {{selected_eggprod_record.total_egg_weight}}</span><br>
                                    <span>Broken : {{selected_eggprod_record.total_broken}}</span><br>
                                    <span>Rejected : {{selected_eggprod_record.total_rejects}}</span><br>
                                    <span>Remarks : {{selected_eggprod_record.remarks}}</span><br>
                                </blockquote>
                                <p>This action is <strong>Irreversible</strong>.</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a @click.prevent="deleteFeedingRecord" href="javascript:void(0)" class="modal-action modal-close waves-effect waves-grey btn-flat">Yes</a>
                        <a href="javascript:void(0)" class="modal-action modal-close waves-effect waves-grey btn-flat">No</a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script>
    import Datepicker from 'vuejs-datepicker';
    export default {
        components : {
            Datepicker,
        },
        props :{
            breeder : Object,
            animal_type : Number
        },
        data (){
            return {
                eggprod : {},
                eggprod_length : 0,
                date_collected : '',
                total_eggs_intact : '',
                total_egg_weight : '',
                total_broken : '',
                total_rejects : '',
                remarks : '',
                add_record : null,
                selected_record : null,
                hide_record : false,
                selected_eggprod_record : '',
                check_date_collected : true,
                check_total_eggs_intact : true,
                check_total_egg_weight : true,
                check_total_broken : true,
                check_total_rejects : true,

                edit_date_collected : '',
                edit_total_eggs_intact : '',
                edit_total_egg_weight : '',
                edit_total_broken : '',
                edit_total_rejects : '',
                edit_remarks : '',
                edit_selected_inventory : '',
                edit_selected_eggprod_record : '',
                edit_check_date_collected : true,
                edit_check_total_eggs_intact : true,
                edit_check_total_egg_weight : true,
                edit_check_total_broken : true,
                edit_check_total_rejects : true,

            }
        },
        methods : {
            initialize : function () {
                this.fetchEggProductionRecord();
            },
            fetchEggProductionRecord : function (page = 1) {
                axios.get('breeder_eggprod/'+this.breeder.inventory_id+"?page="+page)
                .then(response => {
                    this.eggprod = response.data;
                    this.eggprod_length = this.eggprod.data.length;
                })
                .catch(function (error) {
                    console.log(error);
                });
            },
            addEggProduction : function () {
                axios.post('breeder_add_eggprod', {
                    breeder_id: this.breeder.inventory_id,
                    date_added : this.dateFormatter(this.date_collected),
                    total_eggs_intact : this.total_eggs_intact,
                    total_egg_weight : this.total_egg_weight,
                    total_broken : this.total_broken,
                    total_rejects : this.total_rejects,
                    remarks : this.egg_prod_remarks,
                })
                .then(response => {
                    if(response.data.error == undefined){
                        this.date_collected = '';
                        this.total_eggs_intact = '';
                        this.total_egg_weight = '';
                        this.total_broken = '';
                        this.total_rejects = '';
                        this.remarks = '';
                        Materialize.toast('Successfully added egg production record', 5000, 'green rounded');
                    }else{
                        Materialize.toast(response.data.error, 5000, 'red rounded');
                    }

                })
                .catch(error => {
                    Materialize.toast('Failed to add egg production record', 5000, 'red rounded');
                });
                this.initialize();
            },
            initializeEdit : function (data) {
                this.edit_selected_inventory = data.inventory_id;
                this.edit_selected_eggprod_record = data.eggprod_id;
                this.edit_date_collected = data.date_collected;
                this.edit_total_eggs_intact = data.total_eggs_intact;
                this.edit_total_egg_weight = data.total_egg_weight;
                this.edit_total_broken = data.total_broken;
                this.edit_total_rejects = data.total_rejects;
                this.edit_remarks = data.remarks;
            },
            uninitializeEdit : function () {
                this.edit_selected_inventory = '';
                this.edit_selected_eggprod_record = '';
                this.edit_date_collected = '';
                this.edit_total_eggs_intact = '';
                this.edit_total_egg_weight = '';
                this.edit_total_broken = '';
                this.edit_total_rejects = '';
                this.edit_remarks = '';
            },
            validateEdit : function () {
                if(this.edit_date_collected === "") {this.edit_check_date_collected = false;} else {this.edit_check_date_collected = true;}
                if(this.edit_total_eggs_intact === "") {this.edit_check_total_eggs_intact = false;} else {this.edit_check_total_eggs_intact = true;}
                if(this.edit_total_egg_weight === "") {this.edit_check_total_egg_weight = false;} else {this.edit_check_total_egg_weight = true;}
                if(this.edit_total_broken === "") {this.edit_check_total_broken = false;} else {this.edit_check_total_broken = true;}
                if(this.edit_total_rejects === "") {this.edit_check_total_rejects = false;} else {this.edit_check_total_rejects = true;}
                if(this.edit_check_date_collected === false || this.edit_check_total_eggs_intact === false || this.edit_check_total_egg_weight === false || this.edit_check_total_broken === false || this.edit_check_total_rejects === false){
                    return;
                }else{
                    this.editEggProduction();
                }
            },
            editEggProduction : function () {
                axios.patch('breeder_edit_eggprod', {
                    inventory_id : this.edit_selected_inventory,
                    record_id: this.edit_selected_eggprod_record,
                    date_added : this.dateFormatter(this.edit_date_collected),
                    total_eggs_intact : this.edit_total_eggs_intact,
                    total_egg_weight : this.edit_total_egg_weight,
                    total_broken : this.edit_total_broken,
                    total_rejects : this.edit_total_rejects,
                    remarks : this.edit_remarks,
                })
                .then(response => {
                    if(response.data.error == undefined){
                        Materialize.toast('Successfully added egg production record', 5000, 'green rounded');
                    }else{
                        Materialize.toast(response.data.error, 5000, 'red rounded');
                    }

                })
                .catch(error => {
                    Materialize.toast('Failed to add egg production record', 5000, 'red rounded');
                });
                this.initialize();
            },
            deleteFeedingRecord : function () {
                axios.delete('breeder_delete_eggprod/' + this.selected_eggprod_record.eggprod_id)
                .then(response => {
                    this.selected_eggprod_record = '';
                    Materialize.toast('Successfully deleted egg production record', 5000, 'green rounded');
                })
                .catch(error => {
                    Materialize.toast('Failed to delete egg production record', 5000, 'red rounded');
                });
                this.fetchEggProductionRecord();
            },
            validateInput : function () {
                if(this.date_collected === "") {this.check_date_collected = false;} else {this.check_date_collected = true;}
                if(this.total_eggs_intact === "") {this.check_total_eggs_intact = false;} else {this.check_total_eggs_intact = true;}
                if(this.total_egg_weight === "") {this.check_total_egg_weight = false;} else {this.check_total_egg_weight = true;}
                if(this.total_broken === "") {this.check_total_broken = false;} else {this.check_total_broken = true;}
                if(this.total_rejects === "") {this.check_total_rejects = false;} else {this.check_total_rejects = true;}
                if(this.check_date_collected === false || this.check_total_eggs_intact === false || this.check_total_egg_weight === false || this.check_total_broken === false || this.check_total_rejects === false){
                    return;
                }else{
                    this.addEggProduction();
                }
            },
            dateFormatter : function (date) {
                return moment(date).format('YYYY-MM-DD');
            },
            closeEggProduction: function () {
                this.$emit('close_eggprod', null)
            }
        },
        mounted () {
            this.initialize();
            $('#delete_eggprod').modal({
                dismissible : false,
            });
            $('#edit_eggprod').modal({
                dismissible : false,
            });
        }

    }
</script>

<style scoped>
    .input-field input[type=number]:focus + label {
        color: #607d8b;
    }
    .input-field input[type=number]:focus {
        border-bottom: 1px solid #607d8b;
        box-shadow: 0 1px 0 0 #546e7a;
    }
    .input-field input[type=text]:focus + label {
        color: #607d8b;
    }
    .input-field input[type=text]:focus {
        border-bottom: 1px solid #607d8b;
        box-shadow: 0 1px 0 0 #546e7a;
    }
</style>
