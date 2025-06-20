<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 500 200"
    {{ $attributes->merge(['class' => 'block h-20 mt-6 w-auto']) }}
    fill="none"
    stroke-width="10"
>
  <g fill="none" stroke-width="10">
    <!-- Líneas izquierda -->
    <line x1="20" y1="40" x2="100" y2="40" stroke="purple"/>
    <line x1="20" y1="70" x2="100" y2="70" stroke="green"/>
    <line x1="20" y1="100" x2="100" y2="100" stroke="purple"/>

    <!-- Líneas derecha -->
    <line x1="400" y1="40" x2="480" y2="40" stroke="purple"/>
    <line x1="400" y1="70" x2="480" y2="70" stroke="green"/>
    <line x1="400" y1="100" x2="480" y2="100" stroke="purple"/>
  </g>

  <!-- Texto superior -->
  <text x="210" y="45" font-family="Arial" font-size="24" font-weight="bold" fill="black">
    PERFUMERÍA
  </text>

  <!-- Texto central principal -->
  <text x="130" y="110" font-family="'Sacramento', cursive" font-size="70" fill="purple">
    Mariam
  </text>
</svg>
