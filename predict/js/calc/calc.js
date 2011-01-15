function get_value(id) {
    return parseFloat(document.getElementById(id).value);
}

function clear_errors() {
    var ids = ['mp', 'tar', 'tba', 'rho_g', 'rho_a', 'adm', 'bd', 'cd',
        'bd_c', 'cd_c'];

    for(var i in ids) {
        document.getElementById(ids[i]).style.backgroundColor = '';
    }

    var ids = ['mp_w', 'mb_w', 'tar_w', 'tba_w'];
    for(i in ids) {
        document.getElementById(ids[i]).innerHTML = '&nbsp;';
    }
}

function show_error(id) {
    document.getElementById(id).style.backgroundColor = '#f99';
}

function set_error(id, error) {
    show_error(id);
    document.getElementById(id+"_w").innerHTML = error;
}

function sanity_check_inputs(mb, mp, mp_set, tar, tba, tar_set, tba_set) {
    if(tar_set && tba_set) {
        set_error('tar', "Can't specify both!");
        set_error('tba', "Can't specify both!");
        return 1;
    } else if(!tar_set && !tba_set) {
        set_error('tar', "Must specify at least one!");
        set_error('tba', "Must specify at least one!");
        return 1;
    }

    if(tar_set && tar < 0) {
        set_error('tar', "Can't be negative!");
        return 1;
    } else if(tar_set && tar > 10) {
        set_error('tar', "Too large! (> 10m/s)");
        return 1;
    }

    if(tba_set && tba < 10000) {
        set_error('tba', "Too low! (< 10km)");
        return 1;
    } else if(tba_set && tba > 40000) {
        set_error('tba', "Too high! (> 40km)");
        return 1;
    }

    if(!mp_set) {
        set_error('mp', "Mass required!");
        return 1;
    } else if(mp < 20) {
        set_error('mp', "Too small! (< 20g)");
        return 1;
    } else if(mp > 5000) {
        set_error('mp', "Too large! (> 5kg)");
        return 1;
    }

    return 0;

}

function sanity_check_constants(rho_g, rho_a, adm, ga, bd, cd) {
    if(!rho_a || rho_a < 0) {
        show_error('rho_a');
        return 1;
    }
    if(!rho_g || rho_g < 0 || rho_g > rho_a) {
        show_error('rho_g');
        return 1;
    }
    if(!adm || adm < 0) {
        show_error('adm');
        return 1;
    }
    if(!ga || ga < 0) {
        show_error('ga');
        return 1;
    }
    if(!cd || cd < 0 || cd > 1) {
        show_error('cd');
        return 1;
    }
    if(!bd || bd < 0) {
        show_error('bd');
        return 1;
    }

    return 0;
}

function find_rho_g() {
    var gas = document.getElementById('gas').value;
    var rho_g;

    switch(gas) {
        case 'he':
            rho_g = 0.1786;
            document.getElementById('rho_g').value = rho_g;
            document.getElementById('rho_g').disabled = "disabled";
            break;
        case 'h':
            rho_g = 0.0899;
            document.getElementById('rho_g').value = rho_g;
            document.getElementById('rho_g').disabled = "disabled";
            break;
        case 'ch4':
            rho_g = 0.6672;
            document.getElementById('rho_g').value = rho_g;
            document.getElementById('rho_g').disabled = "disabled";
            break;
        default:
            document.getElementById('rho_g').disabled = "";
            rho_g = get_value('rho_g');
            break;
    }

    return rho_g;
}

function find_bd(mb) {
    var bds = new Array();

    // From Kaymont Totex Sounding Balloon Data
    bds[200] = 3.00;
    bds[300] = 3.78;
    bds[350] = 4.12;
    bds[450] = 4.72;
    bds[500] = 4.99;
    bds[600] = 6.02;
    bds[700] = 6.53;
    bds[800] = 7.00;
    bds[1000] = 7.86;
    bds[1200] = 8.63;
    bds[1500] = 9.44;
    bds[2000] = 10.54;
    bds[3000] = 13.00;
 
    var bd_c = document.getElementById('bd_c').checked;
    var bd;

    if(bd_c) {
        bd = get_value('bd');
        document.getElementById('bd').disabled = "";
    } else {
        bd = bds[mb];
        document.getElementById('bd').disabled = "disabled";
        document.getElementById('bd').value = bd;
    }

    return bd;
}

function find_cd(mb) {
    var cds = new Array();

    // From Kaymont Totex Sounding Balloon Data
    cds[200] = 0.25;
    cds[300] = 0.25;
    cds[350] = 0.25;
    cds[450] = 0.25;
    cds[500] = 0.25;
    cds[600] = 0.30;
    cds[700] = 0.30;
    cds[800] = 0.30;
    cds[1000] = 0.30;
    cds[1200] = 0.25;
    cds[1500] = 0.25;
    cds[2000] = 0.25;
    cds[3000] = 0.25;

    var cd_c = document.getElementById('cd_c').checked;
    var cd;

    if(cd_c) {
        cd = get_value('cd');
        document.getElementById('cd').disabled = "";
    } else {
        cd = cds[mb];
        document.getElementById('cd').disabled = "disabled";
        document.getElementById('cd').value = cd;
    }

    return cd;
}

function calc_update() {
    // Reset error status
    clear_errors();

    // Get input values and check them
    var mb = get_value('mb');
    var mp = get_value('mp');
    var tar = get_value('tar');
    var tba = get_value('tba');
    var mp_set = 0;
    var tar_set = 0;
    var tba_set = 0;

    if(document.getElementById('mp').value)
        mp_set = 1;
    if(document.getElementById('tar').value)
        tar_set = 1;
    if(document.getElementById('tba').value)
        tba_set = 1;

    if(sanity_check_inputs(mb, mp, mp_set, tar, tba, tar_set, tba_set))
        return;

    // Get constants and check them
    var rho_g = find_rho_g();
    var rho_a = get_value('rho_a');
    var adm = get_value('adm');
    var ga = get_value('ga');
    var bd = find_bd(mb);
    var cd = find_cd(mb);

    if(sanity_check_constants(rho_g, rho_a, adm, ga, bd, cd))
        return;
    
    // Do some maths
    mb = mb / 1000.0;
    mp = mp / 1000.0;

    var ascent_rate = 0;
    var burst_altitude = 0;
    var time_to_burst = 0;
    var neck_lift = 0;
    var launch_radius = 0;
    var launch_volume = 0;

    var burst_volume = (4.0/3.0) * Math.PI * Math.pow(bd / 2.0, 3);

    if(tba_set) {
        launch_volume = burst_volume * Math.exp((-tba) / adm);
        launch_radius = Math.pow((3*launch_volume)/(4*Math.PI), (1/3));
    } else if(tar_set) {
        var a = ga * (rho_a - rho_g) * (4.0 / 3.0) * Math.PI;
        var b = -0.5 * Math.pow(tar, 2) * cd * rho_a * Math.PI;
        var c = 0;
        var d = - (mp + mb) * ga;

        var f = (((3*c)/a) - (Math.pow(b, 2) / Math.pow(a,2)) / 3.0);
        var g = (
            ((2*Math.pow(b,3))/Math.pow(a,3)) -
            ((9*b*c)/(Math.pow(a,2))) + ((27*d)/a) / 27.0
        );
        var h = (Math.pow(g,2) / 4.0) + (Math.pow(f,3) / 27.0);

        if(h>0) {
            // One real root. This is what should happen.
            var R = (-0.5 * g) + Math.sqrt(h);
            var S = Math.pow(R, 1.0/3.0);
            var T = (-0.5 * g) - Math.sqrt(h);
            var U = Math.pow(T, 1.0/3.0);
            launch_radius = (S+U) - (b/(3*a));
        } else if(f==0 && g==0 && h==0) {
            // Three real and equal roots
            // Will this ever even happen?
            launch_radius = -1 * Math.pow(d/a, 1.0/3.0);
        } else if(h <= 0) {
            // Three real and different roots
            // What the hell do we do?!
            // It needs trig! fffff
            var i = Math.sqrt((Math.pow(g,2)/4.0) - h);
            var j = Math.pow(i, 1.0/3.0);
            var k = Math.acos(-g / (2*i));
            var L = -1 * j;
            var M = Math.cos(K/3.0);
            var N = Math.sqrt(3) * Math.sin(K/3.0);
            var P = (b/(3*a)) * -1;
            var r1 = 2*j*Math.cos(k/3.0) - (b/(3*a));
            var r2 = L * (M + N) + P;
            var r3 = L * (M - N) + P;

            alert("Three possible solutions found: "
                + r1 + ", " + r2 + ", " + r3);
            
            if(r1 > 0) {
                launch_radius = r1;
            } else if(r2 > 0) {
                launch_radius = r2;
            } else if(r3 > 0) {
                launch_radius = r3;
            }
        } else {
            // No real roots
        }
    }

    var launch_area = Math.PI * Math.pow(launch_radius, 2);
    var launch_volume = (4.0/3.0) * Math.PI * Math.pow(launch_radius, 3);
    var density_difference = rho_a - rho_g;
    var gross_lift = launch_volume * density_difference;
    neck_lift = (gross_lift - mb) * 1000;
    var total_mass = mp + mb;
    var free_lift = (gross_lift - total_mass) * ga;
    ascent_rate = Math.sqrt(free_lift / (0.5 * cd * launch_area * rho_a));
    var volume_ratio = launch_volume / burst_volume;
    burst_altitude = -(adm) * Math.log(volume_ratio);
    time_to_burst = (burst_altitude / ascent_rate) / 60.0;

    if(isNaN(ascent_rate)) {
        set_error('tba', "Altitude unreachable");
        return;
    }

    ascent_rate = ascent_rate.toFixed(2);
    burst_altitude = burst_altitude.toFixed();
    time_to_burst = time_to_burst.toFixed();
    neck_lift = neck_lift.toFixed();
    launch_litres = (launch_volume * 1000).toFixed();
    launch_cf = (launch_volume * 35.31).toFixed(1);
    launch_volume = launch_volume.toFixed(2);

    document.getElementById('ar').innerHTML = ascent_rate;
    document.getElementById('ba').innerHTML = burst_altitude;
    document.getElementById('ttb').innerHTML = time_to_burst;
    document.getElementById('nl').innerHTML = neck_lift;
    document.getElementById('lv_m3').innerHTML = launch_volume;
    document.getElementById('lv_l').innerHTML = launch_litres;
    document.getElementById('lv_cf').innerHTML = launch_cf;
}

function show_help() {
    hide_about();
    document.getElementById('helpbox').style.visibility = 'visible';
    return false;
}

function show_about() {
    hide_help();
    document.getElementById('aboutbox').style.visibility = 'visible';
    return false;
}

function hide_help() {
    document.getElementById('helpbox').style.visibility = 'hidden';
    return false;
}

function hide_about() {
    document.getElementById('aboutbox').style.visibility = 'hidden';
    return false;
}

function calc_init() {

    var ids = ['mb', 'mp', 'tar', 'tba', 'gas', 'rho_g', 'rho_a', 'adm', 'bd', 'cd', 'bd_c', 'cd_c'];
    for(var i in ids) {
        document.getElementById(ids[i]).onchange = calc_update;
    }
    calc_update();
}
