<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Agni Car rental</title>
  <style>
    body {
      background-color: #1c1c1c;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .car {
      position: relative;
      width: 200px;
      height: 60px;
      background-color: #3498db;
      border-radius: 10px 10px 0 0;
    }

    .car::before {
      content: '';
      position: absolute;
      top: -30px;
      left: 30px;
      width: 140px;
      height: 40px;
      background-color: #3498db;
      border-radius: 10px;
    }

    .wheel {
      position: absolute;
      bottom: -20px;
      width: 40px;
      height: 40px;
      background-color: black;
      border-radius: 50%;
    }

    .wheel.left {
      left: 20px;
    }

    .wheel.right {
      right: 20px;
    }

    .headlight {
      position: absolute;
      top: 15px;
      width: 20px;
      height: 20px;
      
      border-radius: 50%;
      
    }

    .headlight.left {
      left: -10px;
      
    }

    .headlight.right {
      right: -10px;
      background-color: yellow;
      box-shadow: 0 0 20px 8px yellow;
    }
  </style>
</head>
<body>
    <h1 id="arbaz">HELLO ARBAZ</h1>
  <div class="car">
    <div class="wheel left"></div>
    <div class="wheel right"></div>
    <div class="headlight left"></div>
    <div class="headlight right"></div>
  </div>
<script>
    let v0 = document.getElementById("arbaz");
    console.log(v0);
    v0.style.textAlign = "center";
    v0.style.backgroundColor = "white";
    v0.style.color = "black";
    v0.style.fontSize = "24px";
    v0.style.fontWeight = "bold";
    v0.style.position = "absolute";
    v0.style.top = "10px";
    v0.style.width = "100%";

    
    let v=document.querySelector(".car");
    let v1 = document.querySelector(".headlight.right"); 
    let flag=1;
    let count=0;
    v.addEventListener("click",function(){
        if (flag==1){
        v1.style.backgroundColor="white";
        v1.style.boxShadow = "0 0 20px 8px";
        console.log(count);
        count+=1;
        flag=0;
        }
        else{
            v1.style.backgroundColor="Yellow";
            v1.style.boxShadow = "0 0 20px 8px yellow";
            console.log(count);
            flag=1;
            count+=1;
        }
    });
    
</script>
</body>
</html>
