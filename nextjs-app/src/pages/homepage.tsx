"use client";
import React, {useState} from "react";
import Link from "next/link";

const ExampleApp = () => {
  const headerText = "React";
  const [contentText, setContentText] = useState(
    "This page is generated using React and Typescript. See the source code at "
  );
  const sourceText = "https://github.com/J0hnRJr/jlrenodin.software-Source/blob/main/nextjs-app/src/app/page.tsx";
  const [count, setCount] = useState(0);

  return (
    <div style={{padding:'1%'}}>
      <h1>{headerText}</h1>
      <p>
        {contentText}
        <Link 
          style={{backgroundColor:'green',width:'fit-content', paddingInline:'5px'}} 
          href={sourceText}
        >{"this page"}
        </Link>
      </p>
      <button onClick={()=> {
        setCount((prev)=>{return prev+1});
        setContentText("You have changed the page content " + count + " times!");
      }}>Click here to change the page</button>
      <p><a href="https://jlrenodin.software/">Home</a> | <a href="https://www.jlrenodin.software/pages/E-Portfolio.php">Go back</a></p>
    </div>
  );
};

export default ExampleApp;
